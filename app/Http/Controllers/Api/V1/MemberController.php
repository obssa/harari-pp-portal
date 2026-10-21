<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Api\Models\ApiKey;
use App\Domain\Api\Support\ApiPresenter;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentTranslation;
use App\Domain\Documents\Support\MembersOnlyFile;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Membership\Actions\IssueMemberToken;
use App\Domain\Membership\Actions\SignInMember;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipApplication;
use App\Domain\Membership\Models\MemberToken;
use App\Domain\Organisation\Queries\PublicDirectory;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The member app (scope `members`, ADR-0057): sign-in with the same SMS code
 * as the member portal, then the member's card, details, event
 * registrations and members-only documents with their token.
 */
final class MemberController extends ApiController
{
    public function __construct(private readonly ApiPresenter $present) {}

    /** Step 1: a code by SMS, when the number is a member's (the answer never says). */
    public function requestCode(Request $request, SignInMember $signIn): JsonResponse
    {
        $signIn->requestCode((string) $request->input('phone', ''), $this->locale());

        return response()->json(['message' => __('api.member.code_sent', ['minutes' => SignInMember::CODE_MINUTES])], 202);
    }

    /** Step 2: the code for a token the app keeps and sends as a Bearer token. */
    public function token(Request $request, SignInMember $signIn, IssueMemberToken $tokens): JsonResponse
    {
        $phone = PhoneNumber::normalise((string) $request->input('phone', ''));
        if ($phone === null) {
            throw ValidationException::withMessages(['phone' => __('events.registration.validation.phone')]);
        }

        $member = $signIn->verify(MembershipApplication::hash($phone), (string) $request->input('code', ''));
        $key = $request->attributes->get('api_key');
        [$token, $plain] = $tokens->handle($member, $key instanceof ApiKey ? $key : null, (string) $request->input('device', ''));
        $member->load(['woreda.translations', 'kebele.translations']);

        return response()->json([
            'data' => [
                'token' => $plain,
                'token_type' => 'Bearer',
                'expires_at' => $token->expires_at->format(DATE_ATOM),
                'member' => $this->present->member($member, $this->locale()),
            ],
        ], 201, ['Cache-Control' => 'no-store']);
    }

    public function me(Request $request): JsonResponse
    {
        $member = $this->member($request)->load(['woreda.translations', 'kebele.translations']);

        return $this->one($this->present->member($member, $this->locale()));
    }

    public function registrations(Request $request): JsonResponse
    {
        $locale = $this->locale();
        $registrations = ($this->member($request)->eventRegistrations()?->with('event.translations')->get() ?? collect())
            ->sortByDesc(fn (EventRegistration $registration) => $registration->event->starts_at)
            ->map(fn (EventRegistration $registration): array => $this->present->registration($registration, $locale))
            ->values()
            ->all();

        return response()->json(['data' => $registrations, 'meta' => ['lang' => $locale]]);
    }

    public function documents(Request $request): JsonResponse
    {
        $this->member($request);
        $locale = $this->locale();
        $page = PublicDirectory::visible(Document::query()->where('access', Document::ACCESS_MEMBERS), $locale)
            ->with(['translations.file', 'office.translations'])
            ->orderByDesc('issued_on')->orderByDesc('created_at')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->page($page, fn (Document $document): ?array => ($shown = PublicDirectory::shown($document, $locale)) instanceof DocumentTranslation
            ? $this->withDownload($this->present->document($document, $shown), $document)
            : null);
    }

    public function download(Request $request, string $key): BinaryFileResponse
    {
        $this->member($request);
        [$document] = $this->find(Document::query()->where('access', Document::ACCESS_MEMBERS), $key);
        $document->load('translations.file');

        return MembersOnlyFile::response($document, $this->locale());
    }

    /** Signs out on this device. */
    public function signOut(Request $request, IssueMemberToken $tokens): Response
    {
        $token = $request->attributes->get('member_token');
        if ($token instanceof MemberToken) {
            $tokens->revoke($token);
        }

        return response()->noContent();
    }

    private function member(Request $request): Member
    {
        $member = $request->attributes->get('member');
        abort_unless($member instanceof Member, 401);

        return $member;
    }

    /**
     * The file of a members-only document is fetched through the member API.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withDownload(array $data, Document $document): array
    {
        if (is_array($data['file'] ?? null)) {
            $data['file']['download_url'] = route('api.v1.member.documents.download', ['key' => $document->id]);
        }

        return $data;
    }
}
