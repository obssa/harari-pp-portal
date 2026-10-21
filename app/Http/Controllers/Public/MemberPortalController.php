<?php

namespace App\Http\Controllers\Public;

use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentTranslation;
use App\Domain\Documents\Support\MembersOnlyFile;
use App\Domain\Events\Models\EventRegistration;
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
use App\Support\PhoneNumber;
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
 * The member portal (PLAN.md §4, ADR-0049): sign-in with a code by SMS, the
 * membership card with a QR code anyone can check, the member's details with
 * correction requests, their event registrations and members-only documents.
 * Every page is private and never stored.
 */
final class MemberPortalController extends Controller
{
    private const PENDING = 'member.sign_in';

    public function signIn(Turnstile $turnstile): View|RedirectResponse
    {
        if ($this->member() !== null) {
            return redirect()->route('member.home');
        }

        return view('pages.member.sign-in', ['turnstileKey' => $turnstile->siteKey(), 'fayda' => app(FaydaClient::class)->isConfigured()]);
    }

    public function sendCode(Request $request, SignInMember $signIn, Turnstile $turnstile): RedirectResponse
    {
        SpamGuard::check($request);
        $turnstile->check($request);
        $typed = (string) $request->input('phone', '');
        $hash = $signIn->requestCode($typed, app()->getLocale());
        $request->session()->put(self::PENDING, ['hash' => $hash, 'phone' => PhoneNumber::mask((string) PhoneNumber::normalise($typed)), 'at' => time()]);

        return redirect()->route('member.code');
    }

    public function code(Request $request): View|RedirectResponse
    {
        $pending = $this->pending($request);

        return $pending === null ? redirect()->route('member.sign-in') : view('pages.member.code', ['phone' => $pending['phone']]);
    }

    public function verify(Request $request, SignInMember $signIn): RedirectResponse
    {
        $pending = $this->pending($request);
        if ($pending === null) {
            return redirect()->route('member.sign-in');
        }
        $member = $signIn->verify($pending['hash'], (string) $request->input('code', ''));

        $request->session()->forget(self::PENDING);
        Auth::guard('member')->login($member);
        $request->session()->regenerate();

        return redirect()->route('member.home');
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
     * @return array{hash: string, phone: string, at: int}|null
     */
    private function pending(Request $request): ?array
    {
        $pending = $request->session()->get(self::PENDING);
        if (! is_array($pending) || ! is_string($pending['hash'] ?? null) || time() - (int) ($pending['at'] ?? 0) > SignInMember::CODE_MINUTES * 60) {
            return null;
        }

        return ['hash' => $pending['hash'], 'phone' => (string) ($pending['phone'] ?? ''), 'at' => (int) $pending['at']];
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
