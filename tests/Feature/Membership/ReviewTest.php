<?php

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Enums\SystemRole;
use App\Domain\Membership\Actions\ApplyForMembership;
use App\Domain\Membership\Actions\ReviewApplication;
use App\Domain\Membership\Enums\ApplicationStatus;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Mail\ApplicationDecisionMail;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipApplication;
use App\Domain\Membership\Models\PersonNote;
use App\Domain\Membership\Notifications\NewApplicationNotice;
use App\Domain\Membership\Support\ApplicationExport;
use App\Domain\Membership\Support\DuplicateFinder;
use App\Domain\Membership\Support\Mask;
use App\Domain\Organisation\Actions\SaveOffice;
use App\Domain\Organisation\Models\Office;
use App\Filament\Resources\MembershipApplications\MembershipApplicationResource;
use App\Filament\Resources\MembershipApplications\Pages\ListMembershipApplications;
use App\Filament\Resources\MembershipApplications\Pages\ViewMembershipApplication;
use App\Models\User;
use App\Services\Sms\SendSms;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Livewire\livewire;

function queueWoreda(string $name): Office
{
    return app(SaveOffice::class)->handle(new Office, ['type' => 'woreda', 'translations' => ['en' => ['name' => $name, 'published' => true]]], staff(SystemRole::Publisher));
}

/** A submitted application, as the public form leaves it. */
function submittedApplication(?Office $woreda, array $details = []): MembershipApplication
{
    $phone = $details['phone'] ?? '+2519'.random_int(10000000, 99999999);
    $application = new MembershipApplication([
        'locale' => 'en', 'name' => 'Sample Applicant Person', 'sex' => 'female', 'birth_date' => '1995-03-14', 'birth_year' => 1995,
        'id_type' => 'fayda', 'id_number' => '123456789012', 'id_hash' => MembershipApplication::hash('fayda:123456789012'),
        'education' => 'degree', 'occupation' => 'Sample teacher', 'woreda_id' => $woreda?->id,
        'phone' => $phone, 'phone_hash' => MembershipApplication::hash($phone),
        ...$details,
    ]);
    $application->forceFill([
        'reference' => MembershipApplication::newReference(), 'status' => ApplicationStatus::Submitted,
        'consented_at' => now(), 'phone_verified_at' => now(), 'submitted_at' => now(),
    ])->save();

    return $application;
}

function woredaOfficer(Office $woreda): User
{
    $officer = staff(SystemRole::MembershipOfficer);
    $officer->assignScopedRole(SystemRole::MembershipOfficer, ScopeType::Office, $woreda->id);

    return $officer->refresh();
}

beforeEach(fn () => Bus::fake([SendSms::class]));

it('approves an application: the person becomes a member with the next number and hears it by SMS', function () {
    $north = queueWoreda('Sample Woreda North');
    $officer = woredaOfficer($north);
    $first = submittedApplication($north);
    $second = submittedApplication($north, ['id_hash' => null, 'id_number' => null, 'birth_year' => 1990, 'birth_date' => '1990-01-01', 'name' => 'Other Sample Person']);

    $member = app(ReviewApplication::class)->approve($first, $officer);
    $next = app(ReviewApplication::class)->approve($second, $officer);

    expect($member->member_no)->toBe('HPP-000001')->and($next->member_no)->toBe('HPP-000002')
        ->and($member->status)->toBe(MemberStatus::Active)
        ->and($member->name)->toBe('Sample Applicant Person')
        ->and($member->woreda_id)->toBe($north->id)
        ->and($member->application_id)->toBe($first->id)
        ->and($member->renewal_due_on->toDateString())->toBe(CarbonImmutable::now()->addMonthsNoOverflow(12)->toDateString())
        ->and($first->refresh()->status)->toBe(ApplicationStatus::Approved)
        ->and($first->reviewer_id)->toBe($officer->id)
        ->and($first->notes()->sole()->kind)->toBe(PersonNote::DECISION);

    $raw = json_encode((array) DB::table('members')->where('id', $member->id)->first());
    expect($raw)->not->toContain('Sample Applicant Person')->not->toContain('123456789012');
    expect(json_encode((array) DB::table('people_notes')->first()))->not->toContain('HPP-000001');
    Bus::assertDispatched(SendSms::class, fn (SendSms $sms): bool => $sms->to === $first->phone && str_contains($sms->text, 'HPP-000001'));

    expect(fn () => app(ReviewApplication::class)->approve($first->refresh(), $officer))->toThrow(ValidationException::class);
    expect(Member::query()->count())->toBe(2);
});

it('rejects with a reason the applicant sees on the status page and by email', function () {
    Mail::fake();
    $north = queueWoreda('Sample Woreda North');
    $officer = woredaOfficer($north);
    $application = submittedApplication($north, ['phone' => '+251911234567', 'phone_hash' => MembershipApplication::hash('+251911234567'), 'email' => 'applicant@example.org']);

    expect(fn () => app(ReviewApplication::class)->reject($application, '  ', $officer))->toThrow(ValidationException::class);
    app(ReviewApplication::class)->reject($application, 'Sample reason: not resident in the region.', $officer);

    expect($application->refresh()->status)->toBe(ApplicationStatus::Rejected)->and($application->decided_at)->not->toBeNull();
    Bus::assertDispatched(SendSms::class, fn (SendSms $sms): bool => str_contains($sms->text, $application->reference));
    Mail::assertQueued(ApplicationDecisionMail::class, fn (ApplicationDecisionMail $mail): bool => $mail->hasTo('applicant@example.org')
        && ! $mail->approved && $mail->reason === 'Sample reason: not resident in the region.');

    $this->post('/en/membership/status', ['reference' => $application->reference, 'phone' => '0911234567'])
        ->assertOk()->assertSee(__('membership.status.rejected'))->assertSee('Sample reason: not resident in the region.');
    expect(fn () => app(ReviewApplication::class)->approve($application->refresh(), $officer))->toThrow(ValidationException::class);
});

it('shows each officer only the applications of the woredas they look after', function () {
    $north = queueWoreda('Sample Woreda North');
    $south = queueWoreda('Sample Woreda South');
    $northApplication = submittedApplication($north);
    $southApplication = submittedApplication($south);
    $unplaced = submittedApplication(null, ['address_note' => 'Sample village outside the lists']);
    submittedApplication($north)->forceFill(['status' => ApplicationStatus::Started])->save();

    $officer = woredaOfficer($north);
    $this->actingAs($officer);
    expect(MembershipApplicationResource::getEloquentQuery()->pluck('id')->all())->toBe([$northApplication->id]);
    $this->get(MembershipApplicationResource::getUrl('view', ['record' => $northApplication]))->assertOk()->assertSee('Sample Applicant Person');
    $this->get(MembershipApplicationResource::getUrl('view', ['record' => $southApplication]))->assertNotFound();
    expect(fn () => app(ReviewApplication::class)->approve($southApplication, $officer))->toThrow(HttpException::class)
        ->and(fn () => app(ReviewApplication::class)->approve($unplaced, $officer))->toThrow(HttpException::class);

    $everywhere = staff(SystemRole::MembershipOfficer);
    $this->actingAs($everywhere);
    expect(MembershipApplicationResource::getEloquentQuery()->count())->toBe(3);
    $this->get(MembershipApplicationResource::getUrl('view', ['record' => $unplaced]))->assertOk();

    $this->actingAs(staff(SystemRole::Editor));
    $this->get(MembershipApplicationResource::getUrl('index'))->assertForbidden();
});

it('masks names, phones and ID numbers in the queue and records each look at the full ID number', function () {
    $north = queueWoreda('Sample Woreda North');
    $application = submittedApplication($north, ['phone' => '+251911234567', 'phone_hash' => MembershipApplication::hash('+251911234567')]);
    $officer = woredaOfficer($north);
    $this->actingAs($officer);

    expect(Mask::name('Sample Applicant Person'))->toBe('Sample A. P.')
        ->and(Mask::name('Sample Applicant (staging test)'))->toBe('Sample A. S. T.')
        ->and(Mask::name('የናሙና አባል ሰው'))->toBe('የናሙና አ. ሰ.')
        ->and(Mask::phone('+251911234567'))->toBe('+251••••••567')
        ->and(Mask::idNumber('123456789012'))->toBe('••••••••9012');

    $this->get(MembershipApplicationResource::getUrl('index'))->assertOk()
        ->assertSee('Sample A. P.')->assertDontSee('Sample Applicant Person')->assertDontSee('+251911234567');
    $this->get(MembershipApplicationResource::getUrl('view', ['record' => $application]))->assertOk()
        ->assertSee('••••••••9012')->assertDontSee('123456789012');

    livewire(ViewMembershipApplication::class, ['record' => $application->getRouteKey()])
        ->callAction('reveal')->assertNotified(__('membership.admin.revealed'));

    expect($application->notes()->sole()->kind)->toBe(PersonNote::REVEAL)
        ->and(Activity::query()->where('event', 'revealed')->where('subject_id', $application->id)->exists())->toBeTrue();
});

it('decides from the application page and hands an application to another woreda', function () {
    $north = queueWoreda('Sample Woreda North');
    $south = queueWoreda('Sample Woreda South');
    $officer = woredaOfficer($north);
    $this->actingAs($officer);
    $approve = submittedApplication($north);
    $reject = submittedApplication($north, ['id_hash' => null, 'id_number' => null]);
    $move = submittedApplication($north, ['id_hash' => null, 'id_number' => null]);

    livewire(ViewMembershipApplication::class, ['record' => $approve->getRouteKey()])
        ->callAction('approve')->assertHasNoActionErrors()->assertActionHidden('reject');
    expect($approve->refresh()->status)->toBe(ApplicationStatus::Approved)->and(Member::query()->count())->toBe(1);

    livewire(ViewMembershipApplication::class, ['record' => $reject->getRouteKey()])
        ->callAction('reject', ['reason' => ''])->assertHasActionErrors(['reason' => 'required']);
    livewire(ViewMembershipApplication::class, ['record' => $reject->getRouteKey()])
        ->callAction('reject', ['reason' => 'Sample reason'])->assertHasNoActionErrors();
    expect($reject->refresh()->status)->toBe(ApplicationStatus::Rejected);

    livewire(ViewMembershipApplication::class, ['record' => $move->getRouteKey()])
        ->callAction('note', ['body' => 'Sample note: called, lives in the south.'])
        ->callAction('assign', ['woreda_id' => $south->id])
        ->assertRedirect(MembershipApplicationResource::getUrl('index'));
    expect($move->refresh()->woreda_id)->toBe($south->id)
        ->and($move->notes()->pluck('kind')->all())->toBe([PersonNote::ASSIGN, PersonNote::NOTE])
        ->and($move->notes()->where('kind', PersonNote::NOTE)->value('body'))->toBe('Sample note: called, lives in the south.');
    $this->get(MembershipApplicationResource::getUrl('view', ['record' => $move]))->assertNotFound();
    $this->actingAs(woredaOfficer($south))->get(MembershipApplicationResource::getUrl('view', ['record' => $move]))
        ->assertOk()->assertSee('Sample note: called, lives in the south.');
});

it('finds possible duplicates by ID number, phone, and name with date of birth', function () {
    $north = queueWoreda('Sample Woreda North');
    $application = submittedApplication($north, ['phone' => '+251911111111', 'phone_hash' => MembershipApplication::hash('+251911111111')]);
    $samePhone = submittedApplication($north, ['id_hash' => null, 'id_number' => null, 'name' => 'Different Person', 'phone' => '+251911111111', 'phone_hash' => MembershipApplication::hash('+251911111111')]);
    $sameNameAndBirth = submittedApplication($north, ['id_hash' => null, 'id_number' => null, 'name' => '  sample applicant   PERSON ']);
    submittedApplication($north, ['id_hash' => null, 'id_number' => null, 'name' => 'Sample Applicant Person', 'birth_date' => '1995-03-15']);

    $someoneElse = submittedApplication($north, ['name' => 'Someone Else']);
    $member = app(ReviewApplication::class)->approve($someoneElse, staff(SystemRole::MembershipOfficer));

    $matches = collect(DuplicateFinder::for($application));
    expect($matches->pluck('id')->sort()->values()->all())->toBe(collect([$samePhone->id, $sameNameAndBirth->id, $someoneElse->id, $member->id])->sort()->values()->all())
        ->and($matches->firstWhere('id', $samePhone->id)['reasons'])->toBe([__('membership.duplicate.phone')])
        ->and($matches->firstWhere('id', $sameNameAndBirth->id)['reasons'])->toBe([__('membership.duplicate.name_birth')])
        ->and($matches->firstWhere('id', $member->id)['type'])->toBe('member')
        ->and($matches->firstWhere('id', $member->id)['reasons'])->toBe([__('membership.duplicate.id')]);

    // The member made from an application is not its own duplicate.
    expect(collect(DuplicateFinder::for($someoneElse->refresh()))->pluck('id'))->not->toContain($member->id)->toContain($application->id);
});

it('tells the officers of the woreda when an application arrives', function () {
    Notification::fake();
    $north = queueWoreda('Sample Woreda North');
    $south = queueWoreda('Sample Woreda South');
    $northOfficer = woredaOfficer($north);
    $southOfficer = woredaOfficer($south);
    $everywhere = staff(SystemRole::MembershipOfficer);

    $application = submittedApplication($north)->forceFill(['status' => ApplicationStatus::Started, 'submitted_at' => null]);
    $application->forceFill(['code_hash' => MembershipApplication::hash('code:'.$application->id.':123456'), 'code_expires_at' => now()->addMinutes(5), 'code_attempts' => 0])->save();
    app(ApplyForMembership::class)->verify($application, '123456');

    Notification::assertSentTo([$northOfficer, $everywhere], NewApplicationNotice::class, fn (NewApplicationNotice $notice): bool => $notice->reference === $application->reference);
    Notification::assertNotSentTo($southOfficer, NewApplicationNotice::class);
});

it('exports the applications on screen without ID numbers and logs the export', function () {
    $north = queueWoreda('Sample Woreda North');
    $application = submittedApplication($north, ['name' => '=Sample Formula', 'phone' => '+251933333333']);
    submittedApplication(queueWoreda('Sample Woreda South'));
    $officer = woredaOfficer($north);
    $this->actingAs($officer);

    $out = fopen('php://memory', 'w+b');
    ApplicationExport::write(MembershipApplicationResource::getEloquentQuery(), $out);
    rewind($out);
    $csv = (string) stream_get_contents($out);
    expect($csv)->toStartWith("\u{FEFF}\"".__('membership.export.reference').'"')
        ->toContain($application->reference)->toContain("'=Sample Formula")->toContain('+251933333333')->toContain('Sample Woreda North')
        ->not->toContain('123456789012')->not->toContain('Sample Woreda South');

    livewire(ListMembershipApplications::class)->callAction('export')->assertFileDownloaded('membership-applications-'.now()->format('Y-m-d').'.csv');
    expect(Activity::query()->where('event', 'exported')->where('log_name', 'membership')->where('causer_id', $officer->id)->exists())->toBeTrue();
});
