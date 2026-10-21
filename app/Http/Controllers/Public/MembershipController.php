<?php

namespace App\Http\Controllers\Public;

use App\Domain\Membership\Actions\ApplyForMembership;
use App\Domain\Membership\Enums\ApplicationStatus;
use App\Domain\Membership\Models\MembershipApplication;
use App\Domain\Membership\Support\ApplicationOptions;
use App\Domain\Organisation\Enums\OfficeType;
use App\Domain\Organisation\Models\Office;
use App\Domain\Organisation\Queries\PublicDirectory;
use App\Http\Controllers\Controller;
use App\Support\PhoneNumber;
use App\Support\SpamGuard;
use App\Support\Turnstile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The membership application and its status page (PLAN.md §4, ADR-0044).
 * The application in progress is kept in the visitor's session; these pages
 * are never cached or stored.
 */
final class MembershipController extends Controller
{
    private const SESSION = 'membership.application';

    public const STEPS = ['about', 'place', 'confirm'];

    public function apply(Request $request, Turnstile $turnstile): View
    {
        $application = $this->current($request);
        $requested = (string) $request->query('step', '');
        $step = match (true) {
            $application === null => 'about',
            in_array($requested, self::STEPS, true) && $this->reached($application, $requested) => $requested,
            $application->phone === null => 'place',
            default => 'confirm',
        };

        return view('pages.membership.apply', [
            'step' => $step,
            'application' => $application,
            'woredas' => $this->offices(OfficeType::Woreda),
            'kebeles' => $this->offices(OfficeType::Kebele),
            'basicOrganisations' => $this->offices(OfficeType::BasicOrganisation),
            'turnstileKey' => $turnstile->siteKey(),
            'codeSent' => $application?->code_hash !== null && $application->code_expires_at?->isFuture(),
        ]);
    }

    public function about(Request $request, ApplyForMembership $apply, Turnstile $turnstile): RedirectResponse
    {
        if ($this->current($request) === null) {
            SpamGuard::check($request);
            $turnstile->check($request);
        }
        $application = $apply->about($this->current($request), $request->all(), app()->getLocale());
        $request->session()->put(self::SESSION, $application->id);

        return redirect()->route('membership.apply', ['step' => 'place']);
    }

    public function place(Request $request, ApplyForMembership $apply): RedirectResponse
    {
        $apply->place($this->required($request), $request->all());

        return redirect()->route('membership.apply', ['step' => 'confirm']);
    }

    public function sendCode(Request $request, ApplyForMembership $apply): RedirectResponse
    {
        $apply->sendCode($this->required($request), $request->boolean('consent'));

        return redirect()->route('membership.apply', ['step' => 'confirm'])->with('status', __('membership.code_sent'));
    }

    public function verify(Request $request, ApplyForMembership $apply): RedirectResponse
    {
        $application = $apply->verify($this->required($request), (string) $request->input('code', ''));
        $request->session()->forget(self::SESSION);

        return redirect()->route('membership.done')->with('reference', $application->reference);
    }

    public function done(Request $request): View|RedirectResponse
    {
        $reference = $request->session()->get('reference');

        return is_string($reference)
            ? view('pages.membership.done', ['reference' => $reference])
            : redirect()->route('membership.status');
    }

    public function status(Request $request): View
    {
        $found = null;
        $searched = $request->isMethod('POST');
        if ($searched) {
            $reference = MembershipApplication::normaliseReference((string) $request->input('reference', ''));
            $phone = PhoneNumber::normalise((string) $request->input('phone', ''));
            if ($reference !== '' && $phone !== null) {
                $found = MembershipApplication::query()
                    ->where('reference', $reference)
                    ->where('phone_hash', MembershipApplication::hash($phone))
                    ->whereNotIn('status', [ApplicationStatus::Started->value])
                    ->whereNull('erased_at')
                    ->first();
            }
        }

        return view('pages.membership.status', ['searched' => $searched, 'application' => $found]);
    }

    private function current(Request $request): ?MembershipApplication
    {
        $id = $request->session()->get(self::SESSION);
        $application = is_string($id) ? MembershipApplication::query()->find($id) : null;

        return $application instanceof MembershipApplication && $application->status === ApplicationStatus::Started ? $application : null;
    }

    private function required(Request $request): MembershipApplication
    {
        $application = $this->current($request);
        if ($application === null) {
            abort(redirect()->route('membership.apply')->with('status', __('membership.expired')));
        }

        return $application;
    }

    private function reached(MembershipApplication $application, string $step): bool
    {
        return match ($step) {
            'about', 'place' => true,
            default => $application->phone !== null,
        };
    }

    /**
     * @return Collection<string, string> office id => name, in the visitor's language
     */
    private function offices(OfficeType $type): Collection
    {
        $locale = app()->getLocale();

        return PublicDirectory::visible(Office::query()->where('type', $type->value), $locale)
            ->with('translations')->orderBy('sort')->get()
            ->mapWithKeys(fn (Office $office): array => [$office->id => $office->name($locale)])
            ->sort();
    }

    /**
     * The choices the form shows, for the views.
     *
     * @return array{sexes: array<string, string>, idTypes: array<string, string>, education: array<string, string>}
     */
    public static function options(): array
    {
        return [
            'sexes' => ApplicationOptions::sexes(),
            'idTypes' => ApplicationOptions::idTypes(),
            'education' => ApplicationOptions::education(),
        ];
    }
}
