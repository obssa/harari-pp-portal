<?php

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Enums\SystemRole;
use App\Domain\Documents\Actions\SaveDocument;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Models\Document;
use App\Domain\Events\Actions\RegisterForEvent;
use App\Domain\Events\Actions\SaveEvent;
use App\Domain\Events\Models\Event;
use App\Domain\Media\Actions\UploadMedia;
use App\Domain\Membership\Actions\ManageMember;
use App\Domain\Membership\Actions\SaveMember;
use App\Domain\Membership\Actions\SignInMember;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipApplication;
use App\Domain\Membership\Models\PersonNote;
use App\Domain\Membership\Notifications\MemberChangeRequestNotice;
use App\Domain\Organisation\Actions\SaveOffice;
use App\Domain\Organisation\Models\Office;
use App\Http\Middleware\EnsureMemberSignedIn;
use App\Services\Sms\SendSms;
use App\Support\SpamGuard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\TestFiles;

function portalWoreda(): Office
{
    return app(SaveOffice::class)->handle(new Office, ['type' => 'woreda', 'translations' => ['en' => ['name' => 'Sample Woreda North', 'published' => true]]], staff(SystemRole::Publisher));
}

function portalMember(array $details = []): Member
{
    return app(SaveMember::class)->handle(new Member, [
        'name' => 'Sample Portal Member', 'sex' => 'female', 'id_type' => 'kebele', 'id_number' => 'KB777001', 'phone' => '0911777001',
        'joined_on' => '2025-03-01', 'woreda_id' => portalWoreda()->id, ...$details,
    ], staff(SystemRole::Admin));
}

/** The code the last sign-in SMS carried. */
function portalCode(): string
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

function portalStamp(): array
{
    return [SpamGuard::STARTED => Crypt::encryptString((string) (time() - 10))];
}

beforeEach(fn () => Bus::fake([SendSms::class]));

it('signs a member in with a code by SMS and out again', function () {
    $member = portalMember();

    $this->get('/en/member')->assertRedirect(route('member.sign-in', ['locale' => 'en']));
    $this->get('/en/member/sign-in')->assertOk()->assertSee(__('members.portal.sign_in_help'));
    $this->post('/en/member/sign-in', ['phone' => '0911 777 001', ...portalStamp()])->assertRedirect(route('member.code', ['locale' => 'en']));
    $this->get('/en/member/code')->assertOk()->assertSee('+251••••••001');

    $this->post('/en/member/code', ['code' => '000000'])->assertSessionHasErrors('code');
    $this->post('/en/member/code', ['code' => portalCode()])->assertRedirect(route('member.home', ['locale' => 'en']));
    expect(Auth::guard('member')->id())->toBe($member->id);

    $this->get('/en/member')->assertOk()->assertSee($member->member_no)->assertSee('Sample Portal Member')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    $this->post('/en/member/sign-out')->assertRedirect(route('member.sign-in', ['locale' => 'en']));
    $this->get('/en/member')->assertRedirect(route('member.sign-in', ['locale' => 'en']));
});

it('never says whether a number is a member\'s, and only lets active or lapsed members in', function () {
    portalMember(['phone' => '0911777002', 'id_number' => 'KB777002']);
    $suspended = portalMember(['phone' => '0911777003', 'id_number' => 'KB777003']);
    app(ManageMember::class)->changeStatus($suspended, MemberStatus::Suspended, 'Sample suspension', staff(SystemRole::Admin));

    $this->post('/en/member/sign-in', ['phone' => '0911999999', ...portalStamp()])->assertRedirect(route('member.code', ['locale' => 'en']));
    $this->post('/en/member/sign-in', ['phone' => '0911777003', ...portalStamp()])->assertRedirect(route('member.code', ['locale' => 'en']));
    Bus::assertNotDispatchedSync(SendSms::class);

    $lapsed = Member::query()->where('phone_hash', MembershipApplication::hash('+251911777002'))->sole();
    $lapsed->forceFill(['status' => MemberStatus::Lapsed])->save();
    $hash = app(SignInMember::class)->requestCode('0911777002', 'en');
    expect(app(SignInMember::class)->verify($hash, portalCode())->id)->toBe($lapsed->id);
});

it('stops a code after five wrong tries and ends idle sessions', function () {
    $member = portalMember();
    $hash = app(SignInMember::class)->requestCode('0911777001', 'en');
    $code = portalCode();
    foreach (range(1, SignInMember::ATTEMPTS) as $try) {
        expect(fn () => app(SignInMember::class)->verify($hash, '111111'))->toThrow(ValidationException::class);
    }
    expect(fn () => app(SignInMember::class)->verify($hash, $code))->toThrow(ValidationException::class);

    $this->actingAs($member, 'member')->withSession(['member.last_seen' => time() - (EnsureMemberSignedIn::IDLE_MINUTES + 1) * 60])
        ->get('/en/member')->assertRedirect(route('member.sign-in', ['locale' => 'en']));
});

it('shows a printable card whose QR code anyone can check without seeing the name', function () {
    $member = portalMember();
    $this->actingAs($member, 'member');

    $this->get('/en/member/card')->assertOk()->assertSee($member->member_no)->assertSee('data:image/svg+xml;base64,', escape: false);

    $check = Member::cardSignature($member->member_no);
    $this->get("/en/verify/{$member->member_no}/{$check}")->assertOk()->assertSee(__('members.portal.verify_valid'))
        ->assertSee('Sample P. M.')->assertDontSee('Sample Portal Member')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->get("/en/verify/{$member->member_no}/0000000000000000")->assertNotFound()->assertSee(__('members.portal.verify_unknown'));

    $member->forceFill(['status' => MemberStatus::Lapsed])->save();
    $this->get("/en/verify/{$member->member_no}/{$check}")->assertOk()->assertSee(__('members.portal.verify_valid'));
    $member->forceFill(['status' => MemberStatus::Resigned])->save();
    $this->get("/en/verify/{$member->member_no}/{$check}")->assertOk()->assertSee(__('members.portal.verify_not_valid'));
});

it('sends correction requests to the woreda\'s officers without changing the record', function () {
    Notification::fake();
    $member = portalMember();
    $officer = staff(SystemRole::MembershipOfficer);
    $officer->assignScopedRole(SystemRole::MembershipOfficer, ScopeType::Office, $member->woreda_id);
    $this->actingAs($member, 'member');

    $this->get('/en/member/profile')->assertOk()->assertSee('••••7001')->assertDontSee('KB777001');
    $this->post('/en/member/profile', [])->assertSessionHasErrors('note');
    $this->post('/en/member/profile', ['phone' => '0911888999', 'note' => 'Sample: my new number'])->assertRedirect(route('member.profile', ['locale' => 'en']));

    $request = $member->notes()->where('kind', PersonNote::REQUEST)->sole();
    expect($request->body)->toContain('+251911888999')->toContain('Sample: my new number')
        ->and($member->refresh()->phone)->toBe('+251911777001');
    Notification::assertSentTo($officer, MemberChangeRequestNotice::class, fn (MemberChangeRequestNotice $n): bool => $n->memberNo === $member->member_no);

    $this->post('/en/member/profile', ['note' => 'Sample second']);
    $this->post('/en/member/profile', ['note' => 'Sample third']);
    $this->post('/en/member/profile', ['note' => 'Sample fourth'])->assertSessionHasErrors('note');
});

it('lists the member\'s own event registrations', function () {
    $member = portalMember(['email' => 'portal@example.org']);
    $event = app(SaveEvent::class)->handle(new Event, [
        'starts_at' => CarbonImmutable::now()->addDays(3)->setTime(9, 0)->toDateTimeString(), 'registration_enabled' => true,
        'translations' => ['en' => ['title' => 'Sample members forum', 'venue' => 'Sample hall', 'published' => true]],
    ], staff(SystemRole::Publisher));
    $mine = app(RegisterForEvent::class)->handle($event, ['name' => 'Sample Portal Member', 'email' => 'portal@example.org', 'consent' => true], 'en')->registration;
    app(RegisterForEvent::class)->handle($event, ['name' => 'Sample Someone Else', 'email' => 'else@example.org', 'consent' => true], 'en');

    $this->actingAs($member, 'member')->get('/en/member/events')->assertOk()
        ->assertSee('Sample members forum')->assertSee(route('events.ticket', ['locale' => 'en', 'token' => $mine->token]), escape: false)
        ->assertDontSee('Sample Someone Else');
});

it('keeps members-only documents in the portal, never at a public address', function () {
    Storage::fake('media');
    $members = app(UploadMedia::class)->handle(TestFiles::pdf('members.pdf'), 'members.pdf');
    $public = app(UploadMedia::class)->handle(TestFiles::pdf('public.pdf'), 'public.pdf');
    app(SaveDocument::class)->handle(new Document, ['type' => DocumentType::Report->value, 'access' => Document::ACCESS_MEMBERS,
        'translations' => ['en' => ['title' => 'Sample members circular', 'file_id' => $members->id, 'published' => true]]], staff(SystemRole::Publisher));
    app(SaveDocument::class)->handle(new Document, ['type' => DocumentType::Report->value, 'access' => Document::ACCESS_PUBLIC,
        'translations' => ['en' => ['title' => 'Sample public report', 'file_id' => $public->id, 'published' => true]]], staff(SystemRole::Publisher));

    $this->get(route('media.file', ['uuid' => $members->uuid, 'name' => $members->file_name]))->assertNotFound();
    $this->get(route('media.file', ['uuid' => $public->uuid, 'name' => $public->file_name]))->assertOk();
    $this->get('/en/member/documents/sample-members-circular/download')->assertRedirect(route('member.sign-in', ['locale' => 'en']));

    $this->actingAs(portalMember(), 'member');
    $this->get('/en/member/documents')->assertOk()->assertSee('Sample members circular')->assertDontSee('Sample public report');
    $this->get('/en/member/documents/sample-members-circular/download')->assertOk()
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private')->assertDownload('members.pdf');
    $this->get('/en/member/documents/sample-public-report/download')->assertNotFound();
});
