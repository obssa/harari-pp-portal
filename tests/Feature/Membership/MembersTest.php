<?php

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Enums\SystemRole;
use App\Domain\Membership\Actions\ManageMember;
use App\Domain\Membership\Actions\SaveMember;
use App\Domain\Membership\Enums\ApplicationStatus;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipApplication;
use App\Domain\Membership\Models\PersonNote;
use App\Domain\Membership\Support\MemberExport;
use App\Domain\Organisation\Actions\SaveOffice;
use App\Domain\Organisation\Models\Office;
use App\Domain\Privacy\Actions\ErasePersonalData;
use App\Domain\Privacy\Actions\ExportPersonalData;
use App\Domain\Privacy\Support\PersonalDataSearch;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Models\User;
use App\Services\Sms\SendSms;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Livewire\livewire;

function memberWoreda(string $name): Office
{
    return app(SaveOffice::class)->handle(new Office, ['type' => 'woreda', 'translations' => ['en' => ['name' => $name, 'published' => true]]], staff(SystemRole::Publisher));
}

function memberOfficer(Office $woreda): User
{
    $officer = staff(SystemRole::MembershipOfficer);
    $officer->assignScopedRole(SystemRole::MembershipOfficer, ScopeType::Office, $woreda->id);

    return $officer->refresh();
}

/** A member as an officer adds one, with the details given. */
function sampleMember(?Office $woreda, array $details = []): Member
{
    return app(SaveMember::class)->handle(new Member, [
        'name' => 'Sample Member Person', 'sex' => 'male', 'birth_date' => '1980-05-20', 'id_type' => 'kebele', 'id_number' => 'KB'.random_int(100000, 999999),
        'education' => 'diploma', 'occupation' => 'Sample trader', 'woreda_id' => $woreda?->id,
        'phone' => '+2519'.random_int(10000000, 99999999), 'joined_on' => '2025-02-01',
        ...$details,
    ], staff(SystemRole::Admin));
}

beforeEach(fn () => Bus::fake([SendSms::class]));

it('adds members by hand with the next number and a renewal on the next anniversary', function () {
    CarbonImmutable::setTestNow('2026-10-21 10:00');
    $north = memberWoreda('Sample Woreda North');

    $first = sampleMember($north, ['id_number' => 'KB123456', 'phone' => '0911000001']);
    $second = sampleMember($north, ['joined_on' => '2026-10-01']);

    expect($first->member_no)->toBe('HPP-000001')->and($second->member_no)->toBe('HPP-000002')
        ->and($first->source)->toBe(Member::SOURCE_MANUAL)
        ->and($first->status)->toBe(MemberStatus::Active)
        ->and($first->renewal_due_on->toDateString())->toBe('2027-02-01')
        ->and($second->renewal_due_on->toDateString())->toBe('2027-10-01')
        ->and($first->phone)->toBe('+251911000001')
        ->and($first->id_hash)->toBe(MembershipApplication::hash('kebele:KB123456'))
        ->and($first->birth_year)->toBe(1980);

    expect(fn () => sampleMember($north, ['id_number' => 'kb 123456']))->toThrow(ValidationException::class, __('members.validation.duplicate_id', ['number' => 'HPP-000001']))
        ->and(fn () => sampleMember($north, ['birth_date' => '2015-01-01']))->toThrow(ValidationException::class)
        ->and(fn () => sampleMember($north, ['joined_on' => '2027-01-01']))->toThrow(ValidationException::class);

    $south = memberWoreda('Sample Woreda South');
    $officer = memberOfficer($north);
    expect(fn () => app(SaveMember::class)->handle(new Member, ['name' => 'Sample', 'sex' => 'female', 'id_type' => 'none', 'woreda_id' => $south->id, 'joined_on' => '2026-01-01'], $officer))
        ->toThrow(ValidationException::class, __('members.validation.outside_scope'));
    CarbonImmutable::setTestNow();
});

it('lists the members of an officer\'s woredas masked and finds them by number or phone', function () {
    $north = memberWoreda('Sample Woreda North');
    $south = memberWoreda('Sample Woreda South');
    $mine = sampleMember($north, ['phone' => '0911222333']);
    $other = sampleMember($south);
    $this->actingAs(memberOfficer($north));

    expect(MemberResource::getEloquentQuery()->pluck('id')->all())->toBe([$mine->id]);
    $this->get(MemberResource::getUrl('index'))->assertOk()
        ->assertSee('Sample M. P.')->assertDontSee('Sample Member Person')->assertDontSee('+251911222333');
    $this->get(MemberResource::getUrl('view', ['record' => $mine]))->assertOk()->assertSee('Sample Member Person')->assertDontSee((string) $mine->id_number);
    $this->get(MemberResource::getUrl('view', ['record' => $other]))->assertNotFound();

    livewire(ListMembers::class)->searchTable('0911 222 333')->assertCanSeeTableRecords([$mine])
        ->searchTable($mine->member_no)->assertCanSeeTableRecords([$mine])
        ->searchTable('HPP-999999')->assertCanNotSeeTableRecords([$mine]);

    $this->actingAs(staff(SystemRole::Editor))->get(MemberResource::getUrl('index'))->assertForbidden();
});

it('corrects details, recording which ones changed but not their values, and keeps the ID number unless retyped', function () {
    $north = memberWoreda('Sample Woreda North');
    $member = sampleMember($north, ['id_number' => 'KB555555', 'phone' => '0911444555']);
    $this->actingAs(memberOfficer($north));

    $form = livewire(EditMember::class, ['record' => $member->getRouteKey()]);
    expect($form->get('data.name'))->toBe('Sample Member Person')->and($form->get('data.id_number'))->toBeNull();
    $form->fillForm(['name' => 'Sample Member Renamed', 'phone' => '0911444666'])->call('save')->assertHasNoFormErrors();

    $member->refresh();
    $note = $member->notes()->sole();
    expect($member->name)->toBe('Sample Member Renamed')
        ->and($member->id_number)->toBe('KB555555')
        ->and($member->phone_hash)->toBe(MembershipApplication::hash('+251911444666'))
        ->and($note->kind)->toBe(PersonNote::UPDATE)
        ->and($note->body)->toBe(__('members.changed.name').', '.__('members.changed.phone'))
        ->and($note->body)->not->toContain('Renamed');

    livewire(CreateMember::class)->fillForm(['name' => '', 'sex' => 'female', 'id_type' => 'none', 'woreda_id' => $north->id, 'joined_on' => now()->toDateString()])
        ->call('create')->assertHasFormErrors(['name' => 'required']);
});

it('renews from the due date, or from today once it has passed, and changes the status with a reason', function () {
    CarbonImmutable::setTestNow('2026-10-21 10:00');
    $north = memberWoreda('Sample Woreda North');
    $officer = memberOfficer($north);
    $early = sampleMember($north, ['renewal_due_on' => '2026-11-15']);
    $late = sampleMember($north, ['renewal_due_on' => '2026-06-01']);

    expect(app(ManageMember::class)->renew($early, $officer)->toDateString())->toBe('2027-11-15')
        ->and(app(ManageMember::class)->renew($late, $officer)->toDateString())->toBe('2027-10-21')
        ->and($early->refresh()->renewed_on->toDateString())->toBe('2026-10-21');

    expect(fn () => app(ManageMember::class)->changeStatus($early, MemberStatus::Suspended, ' ', $officer))->toThrow(ValidationException::class);
    app(ManageMember::class)->changeStatus($early, MemberStatus::Suspended, 'Sample reason for suspension', $officer);
    expect($early->refresh()->status)->toBe(MemberStatus::Suspended)
        ->and($early->notes()->first()->body)->toContain('Sample reason for suspension')
        ->and(fn () => app(ManageMember::class)->renew($early, $officer))->toThrow(ValidationException::class);

    $this->actingAs($officer);
    livewire(ViewMember::class, ['record' => $late->getRouteKey()])
        ->callAction('status', ['status' => MemberStatus::Resigned->value, 'reason' => 'Sample resignation letter'])->assertHasNoActionErrors()
        ->callAction('note', ['body' => 'Sample note on the member'])
        ->callAction('reveal')->assertNotified(__('membership.admin.revealed'));
    expect($late->refresh()->status)->toBe(MemberStatus::Resigned)
        ->and($late->notes()->pluck('kind')->all())->toBe([PersonNote::REVEAL, PersonNote::NOTE, PersonNote::STATUS, PersonNote::STATUS]);

    $outsider = memberOfficer(memberWoreda('Sample Woreda South'));
    expect(fn () => app(ManageMember::class)->renew($late, $outsider))->toThrow(HttpException::class);
    CarbonImmutable::setTestNow();
});

it('reminds members once before their renewal and marks long-overdue memberships as lapsed', function () {
    CarbonImmutable::setTestNow('2026-10-21 09:00');
    $north = memberWoreda('Sample Woreda North');
    $due = sampleMember($north, ['renewal_due_on' => '2026-11-10', 'phone' => '0911777888']);
    $later = sampleMember($north, ['renewal_due_on' => '2027-03-01']);
    $overdue = sampleMember($north, ['renewal_due_on' => '2026-06-01']);
    $recent = sampleMember($north, ['renewal_due_on' => '2026-10-01']);

    $this->artisan('membership:renewals')->assertSuccessful();
    $this->artisan('membership:renewals')->assertSuccessful();

    Bus::assertDispatchedTimes(SendSms::class, 1);
    Bus::assertDispatched(SendSms::class, fn (SendSms $sms): bool => $sms->to === '+251911777888' && str_contains($sms->text, $due->member_no));
    expect($due->refresh()->renewal_reminded_on?->toDateString())->toBe('2026-10-21')
        ->and($later->refresh()->renewal_reminded_on)->toBeNull()
        ->and($overdue->refresh()->status)->toBe(MemberStatus::Lapsed)
        ->and($overdue->notes()->sole()->kind)->toBe(PersonNote::STATUS)
        ->and($recent->refresh()->status)->toBe(MemberStatus::Active);

    // Renewing starts a new period with its own reminder.
    app(ManageMember::class)->renew($due, staff(SystemRole::Admin));
    expect($due->refresh()->renewal_reminded_on)->toBeNull();
    CarbonImmutable::setTestNow();
});

it('exports the members on screen without ID numbers and logs the export', function () {
    $north = memberWoreda('Sample Woreda North');
    $member = sampleMember($north, ['name' => '@Sample Formula', 'id_number' => 'KB424242']);
    sampleMember(memberWoreda('Sample Woreda South'), ['name' => 'Sample Southern Member']);
    $officer = memberOfficer($north);
    $this->actingAs($officer);

    $out = fopen('php://memory', 'w+b');
    MemberExport::write(MemberResource::getEloquentQuery(), $out);
    rewind($out);
    $csv = (string) stream_get_contents($out);
    expect($csv)->toContain($member->member_no)->toContain("'@Sample Formula")->toContain('Sample Woreda North')
        ->not->toContain('KB424242')->not->toContain('Sample Southern Member');

    livewire(ListMembers::class)->callAction('export')->assertFileDownloaded('members-'.now()->format('Y-m-d').'.csv');
    expect(Activity::query()->where('event', 'exported')->where('causer_id', $officer->id)->where('properties->of', 'members')->exists())->toBeTrue();
});

it('finds, exports and erases membership applications and member records in privacy requests', function () {
    $north = memberWoreda('Sample Woreda North');
    $member = sampleMember($north, ['name' => 'Sample Private Member', 'phone' => '0911999000', 'email' => 'member@example.org', 'id_number' => 'KB909090']);
    app(ManageMember::class)->note($member, 'Sample note naming the member', staff(SystemRole::Admin));
    $application = new MembershipApplication(['locale' => 'en', 'name' => 'Sample Private Member', 'sex' => 'male', 'woreda_id' => $north->id,
        'phone' => '+251911999000', 'phone_hash' => MembershipApplication::hash('+251911999000')]);
    $application->forceFill(['reference' => MembershipApplication::newReference(), 'status' => ApplicationStatus::Rejected, 'submitted_at' => now(), 'decided_at' => now(), 'reason' => 'Sample reason'])->save();
    $other = sampleMember($north, ['name' => 'Sample Other Member']);

    $search = new PersonalDataSearch(null, '0911 999 000');
    expect($search->counts())->toMatchArray(['applications' => 1, 'members' => 1])
        ->and((new PersonalDataSearch('member@example.org', null))->members()->pluck('id')->all())->toBe([$member->id]);

    $export = app(ExportPersonalData::class)->handle($search, staff(SystemRole::Admin));
    expect($export['membership'][0])->toMatchArray(['member_no' => $member->member_no, 'name' => 'Sample Private Member', 'id_number' => 'KB909090'])
        ->and($export['membership'][0]['history'][0]['text'])->toBe('Sample note naming the member')
        ->and($export['membership_applications'][0])->toMatchArray(['reference' => $application->reference, 'reason' => 'Sample reason']);

    $found = app(ErasePersonalData::class)->handle($search, staff(SystemRole::Admin));
    $member->refresh();
    $application->refresh();
    expect($found)->toMatchArray(['applications' => 1, 'members' => 1])
        ->and($member->name)->toBe(ErasePersonalData::ERASED)
        ->and([$member->phone, $member->email, $member->id_number, $member->id_hash, $member->birth_date])->toBe([null, null, null, null, null])
        ->and($member->status)->toBe(MemberStatus::Resigned)
        ->and($member->member_no)->not->toBeEmpty()
        ->and($member->woreda_id)->toBe($north->id)
        ->and($member->notes()->value('body'))->toBe(ErasePersonalData::ERASED)
        ->and([$application->name, $application->phone, $application->reason])->toBe([ErasePersonalData::ERASED, null, null])
        ->and($other->refresh()->name)->toBe('Sample Other Member')
        ->and((new PersonalDataSearch(null, '0911999000'))->erasable())->toBe(0);

    $this->actingAs(staff(SystemRole::Admin))->get(MemberResource::getUrl('view', ['record' => $member]))->assertNotFound();
    expect(json_encode((array) DB::table('members')->where('id', $member->id)->first()))->not->toContain('KB909090');
});

it('removes the history of purged applications with them', function () {
    $application = new MembershipApplication(['locale' => 'en', 'name' => 'Sample Old Applicant']);
    $application->forceFill(['reference' => MembershipApplication::newReference(), 'status' => ApplicationStatus::Rejected, 'decided_at' => now()->subYear()])->save();
    $application->notes()->create(['kind' => PersonNote::DECISION, 'body' => 'Sample decision']);

    $this->artisan('membership:purge-applications')->assertSuccessful();

    expect(MembershipApplication::query()->count())->toBe(0)->and(PersonNote::query()->count())->toBe(0);
});
