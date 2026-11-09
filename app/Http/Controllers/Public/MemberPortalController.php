<?php

namespace App\Http\Controllers\Public;

use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentTranslation;
use App\Domain\Documents\Support\MembersOnlyFile;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Membership\Actions\ChangeMemberPassword;
use App\Domain\Membership\Actions\RequestMemberChange;
use App\Domain\Membership\Actions\SignInMember;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Support\ApplicationOptions;
use App\Domain\Membership\Support\Mask;
use App\Domain\Organisation\Enums\OfficeType;
use App\Domain\Organisation\Models\Office;
use App\Domain\Organisation\Queries\PublicDirectory;
use App\Http\Controllers\Controller;
use App\Services\Fayda\FaydaClient;
use App\Support\QrCode;
use App\Support\SpamGuard;
use App\Support\Turnstile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The member portal (PLAN.md §4, ADR-0049): sign-in with the phone number and
 * password (ADR-0080), the
 * membership card with a QR code anyone can check, the member's details with
 * correction requests, their event registrations and members-only documents.
 * Every page is private and never stored.
 */
final class MemberPortalController extends Controller
{
    public function signIn(Turnstile $turnstile): View|RedirectResponse
    {
        if ($this->member() !== null) {
            return redirect()->route('member.home');
        }

        return view('pages.member.sign-in', ['turnstileKey' => $turnstile->siteKey(), 'fayda' => app(FaydaClient::class)->isConfigured()]);
    }

    /** ADR-0080: the phone number and password; the member's own session from then on. */
    public function authenticate(Request $request, SignInMember $signIn, Turnstile $turnstile): RedirectResponse
    {
        SpamGuard::check($request);
        $turnstile->check($request);
        $member = $signIn->attempt((string) $request->input('phone', ''), (string) $request->input('password', ''));

        Auth::guard('member')->login($member);
        $request->session()->regenerate();

        return redirect()->route('member.home');
    }

    /** Members change their password with the current one (ADR-0080). */
    public function changePassword(Request $request, ChangeMemberPassword $change): RedirectResponse
    {
        $change->handle($this->signedIn(), (string) $request->input('current_password', ''), (string) $request->input('password', ''), (string) $request->input('password_confirmation', ''));
        $request->session()->regenerate();

        return redirect()->route('member.profile')->with('status', __('members.portal.password_changed'));
    }

    public function signOut(Request $request): RedirectResponse
    {
        Auth::guard('member')->logout();
        $request->session()->forget('member.last_seen');
        $request->session()->regenerateToken();

        return redirect()->route('member.sign-in')->with('status', __('members.portal.signed_out'));
    }

    public function home(): View
    {
        $member = $this->signedIn();

        return view('pages.member.home', [
            'member' => $member,
            'fayda' => app(FaydaClient::class)->isConfigured(),
            'dueSoon' => $member->renewal_due_on !== null && $member->renewal_due_on->lessThanOrEqualTo(now()->addDays((int) config('portal.membership_reminder_days'))),
        ]);
    }

    public function card(): View
    {
        $member = $this->signedIn();

        return view('pages.member.card', ['member' => $member, 'qr' => QrCode::dataUri($member->cardUrl())]);
    }

    public function profile(): View
    {
        return view('pages.member.profile', [
            'member' => $this->signedIn(),
            'woredas' => $this->offices(OfficeType::Woreda),
            'kebeles' => $this->offices(OfficeType::Kebele),
            'education' => ApplicationOptions::education(),
        ]);
    }

    public function requestChange(Request $request, RequestMemberChange $requests): RedirectResponse
    {
        $requests->handle($this->signedIn(), $request->only(['phone', 'email', 'woreda_id', 'kebele_id', 'address_note', 'occupation', 'education', 'note']));

        return redirect()->route('member.profile')->with('status', __('members.portal.request_sent'));
    }

    public function events(): View
    {
        $member = $this->signedIn();
        $registrations = ($member->eventRegistrations()?->with('event.translations')->get() ?? collect())
            ->sortByDesc(fn (EventRegistration $r) => $r->event->starts_at)
            ->values();

        return view('pages.member.events', ['registrations' => $registrations]);
    }

    public function documents(): View
    {
        $locale = app()->getLocale();
        $documents = PublicDirectory::visible(Document::query()->where('access', Document::ACCESS_MEMBERS), $locale)
            ->with('translations.file')->orderByDesc('issued_on')->orderByDesc('created_at')->get();

        return view('pages.member.documents', ['documents' => $documents, 'locale' => $locale]);
    }

    public function download(string $slug): BinaryFileResponse
    {
        $this->signedIn();
        $locale = app()->getLocale();
        /** @var DocumentTranslation|null $translation */
        $translation = DocumentTranslation::query()->where('locale', $locale)->where('slug', $slug)->first();
        $document = $translation?->document;
        abort_if($translation === null || $document === null || ! $translation->isLive(), 404);

        return MembersOnlyFile::response($document, $locale);
    }

    /** What anyone who scans a card sees: no personal details beyond initials. */
    public function verifyCard(string $number, string $check): Response
    {
        $member = hash_equals(Member::cardSignature($number), $check)
            ? Member::query()->where('member_no', $number)->whereNull('erased_at')->with('woreda.translations')->first()
            : null;

        return response()->view('pages.member.verify', [
            'member' => $member,
            'number' => $number,
            'initials' => $member !== null ? Mask::name($member->name) : null,
            'valid' => $member?->canUsePortal() === true && $member->renewal_due_on?->isFuture() !== false,
        ], $member === null ? 404 : 200)->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function member(): ?Member
    {
        $member = Auth::guard('member')->user();

        return $member instanceof Member ? $member : null;
    }

    private function signedIn(): Member
    {
        $member = $this->member();
        abort_if($member === null, 403);

        return $member;
    }

    /**
     * @return Collection<string, string>
     */
    private function offices(OfficeType $type): Collection
    {
        $locale = app()->getLocale();

        return PublicDirectory::visible(Office::query()->where('type', $type->value), $locale)
            ->with('translations')->orderBy('sort')->get()
            ->mapWithKeys(fn (Office $office): array => [$office->id => $office->name($locale)])->sort();
    }
}
