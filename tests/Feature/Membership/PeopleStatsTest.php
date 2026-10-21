<?php

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Enums\SystemRole;
use App\Domain\Membership\Actions\ManageMember;
use App\Domain\Membership\Actions\SaveMember;
use App\Domain\Membership\Enums\ApplicationStatus;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipApplication;
use App\Domain\Membership\Support\PeopleStats;
use App\Domain\Organisation\Actions\SaveOffice;
use App\Domain\Organisation\Models\Office;
use App\Domain\Volunteers\Actions\ManageVolunteer;
use App\Domain\Volunteers\Actions\SaveVolunteer;
use App\Filament\Pages\MembershipReports;
use App\Filament\Support\DashboardRegistry;
use App\Filament\Widgets\PeopleBreakdown;
use App\Filament\Widgets\PeopleOverview;
use Carbon\CarbonImmutable;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

function statsWoreda(string $name): Office
{
    return app(SaveOffice::class)->handle(new Office, ['type' => 'woreda', 'translations' => ['en' => ['name' => $name, 'published' => true]]], staff(SystemRole::Publisher));
}

function statsMember(Office $woreda, string $sex, ?int $birthYear, string $joined = '2026-01-15'): Member
{
    return app(SaveMember::class)->handle(new Member, [
        'name' => 'Sample Stats Member', 'sex' => $sex, 'id_type' => 'none', 'woreda_id' => $woreda->id, 'joined_on' => $joined,
        'birth_date' => $birthYear !== null ? "{$birthYear}-06-01" : null,
    ], staff(SystemRole::Admin));
}

beforeEach(fn () => CarbonImmutable::setTestNow('2026-10-21 10:00'));
afterEach(fn () => CarbonImmutable::setTestNow());

it('counts members by woreda, sex, age band, status, source and month, only over the viewer\'s woredas', function () {
    $north = statsWoreda('Sample Woreda North');
    $south = statsWoreda('Sample Woreda South');
    statsMember($north, 'female', 1990, '2026-02-10');
    statsMember($north, 'female', 2005, '2026-02-20');
    statsMember($north, 'male', null, '2026-03-05');
    $resigned = statsMember($north, 'male', 1950);
    app(ManageMember::class)->changeStatus($resigned, MemberStatus::Resigned, 'Sample', staff(SystemRole::Admin));
    statsMember($south, 'male', 1970);

    $admin = new PeopleStats(staff(SystemRole::Admin));
    expect(collect($admin->breakdown('woreda'))->pluck('count', 'label')->all())->toBe(['Sample Woreda North' => 3, 'Sample Woreda South' => 1])
        ->and(collect($admin->breakdown('sex'))->pluck('count', 'key')->all())->toBe(['female' => 2, 'male' => 2])
        ->and(collect($admin->breakdown('age_band'))->pluck('count', 'key')->all())->toBe(['18-24' => 1, '35-44' => 1, '55-64' => 1, 'unknown' => 1])
        ->and(collect($admin->breakdown('status', null))->pluck('count', 'key')->all())->toBe(['active' => 4, 'resigned' => 1])
        ->and(collect($admin->breakdown('source'))->pluck('count', 'key')->all())->toBe(['manual' => 4])
        ->and(collect($admin->breakdown('joined_month', null))->pluck('count', 'key')->all())->toBe(['2026-01' => 2, '2026-02' => 2, '2026-03' => 1])
        ->and(collect($admin->breakdown('woreda', null, CarbonImmutable::parse('2026-02-01'), CarbonImmutable::parse('2026-02-28')))->sum('count'))->toBe(2);

    $officer = staff(SystemRole::MembershipOfficer);
    $officer->assignScopedRole(SystemRole::MembershipOfficer, ScopeType::Office, $south->id);
    $scoped = new PeopleStats($officer->refresh());
    expect($scoped->activeMembers())->toBe(1)
        ->and(collect($scoped->breakdown('woreda'))->pluck('label')->all())->toBe(['Sample Woreda South'])
        ->and((new PeopleStats(staff(SystemRole::Admin), $north->id))->activeMembers())->toBe(3);
});

it('follows applications through the funnel and counts volunteering', function () {
    $north = statsWoreda('Sample Woreda North');
    foreach ([ApplicationStatus::Started, ApplicationStatus::Submitted, ApplicationStatus::Approved, ApplicationStatus::Rejected] as $status) {
        $application = new MembershipApplication(['locale' => 'en', 'name' => 'Sample', 'woreda_id' => $north->id]);
        $application->forceFill(['reference' => MembershipApplication::newReference(), 'status' => $status,
            'submitted_at' => $status === ApplicationStatus::Started ? null : now()])->save();
    }
    $volunteer = app(SaveVolunteer::class)->signUp(['name' => 'Sample Volunteer', 'phone' => '0911000909', 'woreda_id' => $north->id,
        'skills' => ['ushering'], 'availability' => ['weekends'], 'languages' => ['har'], 'consent' => '1'], 'en');
    app(ManageVolunteer::class)->saveAssignment($volunteer, null, ['task' => 'Sample task', 'on_date' => '2026-10-10', 'status' => 'done', 'hours' => '2.5'], staff(SystemRole::Admin));
    app(ManageVolunteer::class)->saveAssignment($volunteer, null, ['task' => 'Sample old task', 'on_date' => '2025-01-10', 'status' => 'done', 'hours' => '9'], staff(SystemRole::Admin));

    $stats = new PeopleStats(staff(SystemRole::Admin));
    $from = CarbonImmutable::now()->subDays(29)->startOfDay();
    expect($stats->funnel($from, CarbonImmutable::now()))->toBe(['started' => 4, 'sent' => 3, 'approved' => 1, 'rejected' => 1])
        ->and($stats->waitingApplications())->toBe(1)
        ->and($stats->volunteering($from, CarbonImmutable::now()))->toBe(['active' => 1, 'new' => 1, 'hours' => 2.5]);
});

it('puts ages in bands and hides small counts in downloads', function () {
    expect(PeopleStats::band(18))->toBe('18-24')->and(PeopleStats::band(25))->toBe('25-34')->and(PeopleStats::band(64))->toBe('55-64')
        ->and(PeopleStats::band(80))->toBe('65+')->and(PeopleStats::band(null))->toBe('unknown')
        ->and(PeopleStats::shown(0))->toBe('0')->and(PeopleStats::shown(3))->toBe('<5')->and(PeopleStats::shown(5))->toBe('5');
});

it('shows the people widgets on the dashboard of those who handle members', function () {
    statsMember(statsWoreda('Sample Woreda North'), 'female', 1990);
    $officer = staff(SystemRole::MembershipOfficer);

    expect(array_column(DashboardRegistry::layoutFor($officer), 'key'))->toContain('people', 'people_breakdown')
        ->and(array_column(DashboardRegistry::layoutFor(staff(SystemRole::Editor)), 'key'))->not->toContain('people');

    $this->actingAs($officer);
    livewire(PeopleOverview::class, ['pageFilters' => ['range' => '30']])
        ->assertSeeInOrder([__('members.dashboard.active'), '1', __('members.dashboard.joined')]);
    livewire(PeopleBreakdown::class, ['pageFilters' => ['range' => '30']])
        ->assertSee(__('members.dashboard.funnel'))->assertSee('Sample Woreda North');
    $this->get('/admin')->assertOk();
});

it('builds the membership report and downloads it without small counts', function () {
    $north = statsWoreda('Sample Woreda North');
    foreach (range(1, 6) as $i) {
        statsMember($north, 'female', 1990);
    }
    statsMember($north, 'male', 1990);
    $admin = staff(SystemRole::Admin);
    $this->actingAs($admin);

    livewire(MembershipReports::class)->assertOk()->assertSee('Sample Woreda North')
        ->filterTable('dimension', 'sex')->assertSee(__('membership.sex.female'))
        ->callTableAction('export')->assertFileDownloaded('membership-sex.csv');
    expect(Activity::query()->where('event', 'exported')->where('properties->of', 'membership_report')->exists())->toBeTrue();

    $out = (new PeopleStats($admin))->breakdown('sex');
    expect(collect($out)->map(fn (array $row): string => PeopleStats::shown($row['count']))->all())->toBe(['6', '<5']);

    $this->actingAs(staff(SystemRole::Editor))->get(MembershipReports::getUrl())->assertForbidden();
});
