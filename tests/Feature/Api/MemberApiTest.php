<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Api\Actions\IssueApiKey;
use App\Domain\Api\Enums\ApiScope;
use App\Domain\Documents\Actions\SaveDocument;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Models\Document;
use App\Domain\Events\Actions\RegisterForEvent;
use App\Domain\Events\Actions\SaveEvent;
use App\Domain\Events\Models\Event;
use App\Domain\Media\Actions\UploadMedia;
use App\Domain\Membership\Actions\IssueMemberToken;
use App\Domain\Membership\Actions\ManageMember;
use App\Domain\Membership\Actions\SaveMember;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberToken;
use App\Domain\Organisation\Actions\SaveOffice;
use App\Domain\Organisation\Models\Office;
use App\Domain\Privacy\Actions\ErasePersonalData;
use App\Domain\Privacy\Support\PersonalDataSearch;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Services\Sms\SendSms;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TestFiles;

use function Pest\Livewire\livewire;

/** A key for the member app (or with other scopes). */
function memberAppKey(ApiScope ...$scopes): string
{
    $scopes = $scopes === [] ? [ApiScope::Members] : $scopes;
    [, $plain] = app(IssueApiKey::class)->handle([
        'name' => 'Sample member app (test)',
        'scopes' => array_map(fn (ApiScope $scope): string => $scope->value, $scopes),
        'rate_per_minute' => 600,
    ], staff(SystemRole::SuperAdmin));

    return $plain;
}

/**
 * @param  array<string, mixed>  $details
 */
function appMember(array $details = []): Member
{
    $woreda = app(SaveOffice::class)->handle(new Office, ['type' => 'woreda', 'translations' => ['en' => ['name' => 'Sample Woreda East', 'published' => true]]], staff(SystemRole::Publisher));

    return app(SaveMember::class)->handle(new Member, [
        'name' => 'Sample App Member', 'sex' => 'male', 'id_type' => 'kebele', 'id_number' => 'KB888001', 'phone' => '0911888001',
        'joined_on' => '2025-02-01', 'woreda_id' => $woreda->id, ...$details,
    ], staff(SystemRole::Admin));
}

/** The six-digit code in the last SMS. */
function appCode(): string
{
    $code = '';
    Bus::assertDispatchedSync(SendSms::class, function (SendSms $sms) use (&$code): bool {
        if (preg_match('/\b(\d{6})\b/', $sms->text, $m) === 1) {
            $code = $m[1];
        }

        return true;
    });

    return $code;
}

/** A member token for this member, issued directly. */
function appToken(Member $member): string
{
    return app(IssueMemberToken::class)->handle($member, null, 'Sample phone')[1];
}

beforeEach(fn () => Bus::fake([SendSms::class]));

it('signs a member in by SMS code, shows the card and signs out', function () {
    $key = memberAppKey();
    $member = appMember();

    $this->postJson('/api/v1/member/sign-in', ['phone' => '0911 888 001'], ['X-Api-Key' => $key])
        ->assertStatus(202)
        ->assertJsonPath('message', __('api.member.code_sent', ['minutes' => 10]));
    $code = appCode();

    $this->postJson('/api/v1/member/token', ['phone' => '0911888001', 'code' => '000000'], ['X-Api-Key' => $key])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    $answer = $this->postJson('/api/v1/member/token', ['phone' => '0911888001', 'code' => $code, 'device' => 'Sample Android phone'], ['X-Api-Key' => $key])
        ->assertCreated()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.member.member_no', $member->member_no)
        ->json('data');
    expect($answer['token'])->toStartWith(MemberToken::PREFIX);

    $stored = MemberToken::query()->sole();
    expect($stored->device)->toBe('Sample Android phone')
        ->and($stored->token_hash)->toBe(MemberToken::hash($answer['token']))
        ->and($stored->getRawOriginal('device'))->not->toContain('Sample');

    $headers = ['X-Api-Key' => $key, 'Authorization' => 'Bearer '.$answer['token']];
    $this->getJson('/api/v1/member/me', $headers)
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.name', 'Sample App Member')
        ->assertJsonPath('data.woreda.name', 'Sample Woreda East')
        ->assertJsonPath('data.card.number', $member->member_no)
        ->assertJsonPath('data.card.verify_url', $member->cardUrl('en'))
        ->assertJsonPath('data.card.valid', true);

    $this->deleteJson('/api/v1/member/token', [], $headers)->assertNoContent();
    expect(MemberToken::query()->count())->toBe(0);
    $this->getJson('/api/v1/member/me', $headers)
        ->assertUnauthorized()
        ->assertJsonPath('message', __('api.error.member_token'));
});

it('answers the same for a number that is not a member\'s and sends nothing', function () {
    $this->postJson('/api/v1/member/sign-in', ['phone' => '0911000999'], ['X-Api-Key' => memberAppKey()])
        ->assertStatus(202)
        ->assertJsonPath('message', __('api.member.code_sent', ['minutes' => 10]));
    Bus::assertNotDispatchedSync(SendSms::class);

    $this->postJson('/api/v1/member/sign-in', ['phone' => 'not a phone'], ['X-Api-Key' => memberAppKey()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');
});

it('needs the members scope, a token, and a member who may still use the portal', function () {
    $member = appMember();
    $this->postJson('/api/v1/member/sign-in', ['phone' => '0911888001'], ['X-Api-Key' => memberAppKey(ApiScope::Content)])->assertForbidden();

    $key = memberAppKey();
    $this->getJson('/api/v1/member/me', ['X-Api-Key' => $key])->assertUnauthorized()->assertHeader('WWW-Authenticate', 'Bearer');
    $this->getJson('/api/v1/member/me', ['X-Api-Key' => $key, 'Authorization' => 'Bearer hppm_wrong'])->assertUnauthorized();

    $token = appToken($member);
    $headers = ['X-Api-Key' => $key, 'Authorization' => 'Bearer '.$token];
    $this->getJson('/api/v1/member/me', $headers)->assertOk();
    // The app's member token is not an API key.
    $this->getJson('/api/v1/member/me', ['X-Api-Key' => $token, 'Authorization' => 'Bearer '.$token])->assertUnauthorized();

    app(ManageMember::class)->changeStatus($member, MemberStatus::Suspended, 'Sample suspension', staff(SystemRole::Admin));
    $this->getJson('/api/v1/member/me', $headers)->assertUnauthorized();

    $other = appToken(appMember(['phone' => '0911888002', 'id_number' => 'KB888002']));
    $this->travel((int) config('portal.api.member_token_days') + 1)->days();
    $this->getJson('/api/v1/member/me', ['X-Api-Key' => $key, 'Authorization' => 'Bearer '.$other])->assertUnauthorized();
});

it('keeps a member signed in on five devices at most', function () {
    $member = appMember();
    $first = appToken($member);
    foreach (range(1, IssueMemberToken::DEVICES) as $ignored) {
        appToken($member);
    }

    expect($member->appTokens()->count())->toBe(IssueMemberToken::DEVICES)
        ->and(MemberToken::findUsable($first))->toBeNull();
});

it('lists the member\'s own event registrations with their tickets', function () {
    $member = appMember(['email' => 'app.member@example.org']);
    $event = app(SaveEvent::class)->handle(new Event, [
        'starts_at' => CarbonImmutable::now()->addDays(3)->setTime(9, 0)->toDateTimeString(), 'registration_enabled' => true,
        'translations' => ['en' => ['title' => 'Sample members forum', 'venue' => 'Sample hall', 'published' => true]],
    ], staff(SystemRole::Publisher));
    $mine = app(RegisterForEvent::class)->handle($event, ['name' => 'Sample App Member', 'email' => 'app.member@example.org', 'consent' => true], 'en')->registration;
    app(RegisterForEvent::class)->handle($event, ['name' => 'Sample Someone Else', 'email' => 'else@example.org', 'consent' => true], 'en');

    $this->getJson('/api/v1/member/registrations', ['X-Api-Key' => memberAppKey(), 'Authorization' => 'Bearer '.appToken($member)])
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.event.title', 'Sample members forum')
        ->assertJsonPath('data.0.event.venue', 'Sample hall')
        ->assertJsonPath('data.0.ticket_url', route('events.ticket', ['locale' => 'en', 'token' => $mine->token]));
});

it('gives members-only documents through the member API only', function () {
    Storage::fake('media');
    $file = app(UploadMedia::class)->handle(TestFiles::pdf('members.pdf'), 'members.pdf');
    $private = app(SaveDocument::class)->handle(new Document, ['type' => DocumentType::Report->value, 'access' => Document::ACCESS_MEMBERS,
        'translations' => ['en' => ['title' => 'Sample members circular', 'file_id' => $file->id, 'published' => true]]], staff(SystemRole::Publisher));
    app(SaveDocument::class)->handle(new Document, ['type' => DocumentType::Report->value,
        'translations' => ['en' => ['title' => 'Sample public report', 'published' => true]]], staff(SystemRole::Publisher));

    $headers = ['X-Api-Key' => memberAppKey(), 'Authorization' => 'Bearer '.appToken(appMember())];
    $this->getJson('/api/v1/member/documents', $headers)
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.title', 'Sample members circular')
        ->assertJsonPath('data.0.file.download_url', route('api.v1.member.documents.download', ['key' => $private->id]));

    $this->get('/api/v1/member/documents/'.$private->id.'/download', $headers)
        ->assertOk()
        ->assertDownload('members.pdf');
    $this->get('/api/v1/member/documents/sample-members-circular/download', $headers)->assertOk();
    $this->getJson('/api/v1/member/documents/sample-public-report/download', $headers)->assertNotFound();
    $this->getJson('/api/v1/member/documents/'.$private->id.'/download', ['X-Api-Key' => $headers['X-Api-Key']])->assertUnauthorized();
});

it('lets officers sign a member out of the app everywhere, and erasure does too', function () {
    $member = appMember();
    appToken($member);
    appToken($member);
    $this->actingAs(staff(SystemRole::Admin));

    livewire(ViewMember::class, ['record' => $member->getRouteKey()])
        ->assertActionVisible('signOutApps')
        ->callAction('signOutApps')
        ->assertNotified(__('members.admin.apps_signed_out'));
    expect($member->appTokens()->count())->toBe(0)
        ->and($member->notes()->first()?->body)->toBe(__('members.note.apps_signed_out', ['count' => 2]));

    livewire(ViewMember::class, ['record' => $member->getRouteKey()])->assertActionHidden('signOutApps');

    appToken($member);
    app(ErasePersonalData::class)->handle(new PersonalDataSearch(null, '0911888001'), staff(SystemRole::Admin));
    expect(MemberToken::query()->count())->toBe(0);
});
