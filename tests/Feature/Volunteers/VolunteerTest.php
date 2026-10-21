<?php

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Enums\SystemRole;
use App\Domain\Events\Actions\SaveEvent;
use App\Domain\Events\Models\Event;
use App\Domain\Membership\Actions\SaveMember;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipApplication;
use App\Domain\Membership\Models\PersonNote;
use App\Domain\Organisation\Actions\SaveOffice;
use App\Domain\Organisation\Models\Office;
use App\Domain\Privacy\Actions\ErasePersonalData;
use App\Domain\Privacy\Actions\ExportPersonalData;
use App\Domain\Privacy\Support\PersonalDataSearch;
use App\Domain\Volunteers\Actions\ManageVolunteer;
use App\Domain\Volunteers\Actions\SaveVolunteer;
use App\Domain\Volunteers\Enums\AssignmentStatus;
use App\Domain\Volunteers\Enums\VolunteerStatus;
use App\Domain\Volunteers\Models\Volunteer;
use App\Domain\Volunteers\Notifications\NewVolunteerNotice;
use App\Filament\Resources\Volunteers\Pages\EditVolunteer;
use App\Filament\Resources\Volunteers\Pages\ListVolunteers;
use App\Filament\Resources\Volunteers\Pages\ViewVolunteer;
use App\Filament\Resources\Volunteers\RelationManagers\AssignmentsRelationManager;
use App\Filament\Resources\Volunteers\VolunteerResource;
use App\Models\User;
use App\Support\SpamGuard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Livewire\livewire;

function volunteerWoreda(string $name): Office
{
    return app(SaveOffice::class)->handle(new Office, ['type' => 'woreda', 'translations' => ['en' => ['name' => $name, 'published' => true]]], staff(SystemRole::Publisher));
}

function coordinatorFor(Office $woreda): User
{
    $officer = staff(SystemRole::MembershipOfficer);
    $officer->assignScopedRole(SystemRole::MembershipOfficer, ScopeType::Office, $woreda->id);

    return $officer->refresh();
}

/** What a person sends from the sign-up form. */
function volunteerAnswers(array $overrides = []): array
{
    return [
        'name' => 'Sample Volunteer Person', 'phone' => '0911 55 66 77', 'email' => '', 'woreda_id' => null,
        'skills' => ['ushering', 'first_aid'], 'availability' => ['weekends'], 'languages' => ['har', 'am'],
        'message' => 'Sample message: I helped at school events.', 'consent' => '1',
        SpamGuard::STARTED => Crypt::encryptString((string) (time() - 10)),
        ...$overrides,
    ];
}

function sampleVolunteer(?Office $woreda, array $overrides = []): Volunteer
{
    return app(SaveVolunteer::class)->signUp(volunteerAnswers(['woreda_id' => $woreda?->id, 'phone' => '+2519'.random_int(10000000, 99999999), ...$overrides]), 'en');
}

it('takes a sign-up from the public form, links a member with the same phone and tells the woreda\'s coordinators', function () {
    Notification::fake();
    $north = volunteerWoreda('Sample Woreda North');
    $south = volunteerWoreda('Sample Woreda South');
    $northCoordinator = coordinatorFor($north);
    $southCoordinator = coordinatorFor($south);
    $member = app(SaveMember::class)->handle(new Member, ['name' => 'Sample Member', 'sex' => 'female', 'id_type' => 'none', 'phone' => '0911556677', 'joined_on' => '2025-01-01', 'woreda_id' => $north->id], staff(SystemRole::Admin));

    $this->get('/en/volunteer')->assertOk()->assertSee(__('volunteers.form.title'))->assertSee(__('volunteers.skill.first_aid'))
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    $this->post('/en/volunteer', volunteerAnswers(['woreda_id' => $north->id]))->assertRedirect(route('volunteer.thanks', ['locale' => 'en']));
    $this->get('/en/volunteer/thanks')->assertOk()->assertSee(__('volunteers.thanks.title'));
    $this->get('/en/volunteer/thanks')->assertRedirect(route('volunteer.create', ['locale' => 'en']));

    $volunteer = Volunteer::query()->sole();
    expect($volunteer->status)->toBe(VolunteerStatus::New)
        ->and($volunteer->phone)->toBe('+251911556677')
        ->and($volunteer->phone_hash)->toBe(MembershipApplication::hash('+251911556677'))
        ->and($volunteer->skills)->toBe(['ushering', 'first_aid'])
        ->and($volunteer->member_id)->toBe($member->id)
        ->and($volunteer->consented_at)->not->toBeNull();
    expect(json_encode((array) DB::table('volunteers')->first()))->not->toContain('Sample Volunteer Person')->not->toContain('251911556677')->not->toContain('school events');

    Notification::assertSentTo($northCoordinator, NewVolunteerNotice::class);
    Notification::assertNotSentTo($southCoordinator, NewVolunteerNotice::class);
});

it('refuses incomplete sign-ups and robots', function () {
    $this->post('/en/volunteer', volunteerAnswers(['skills' => [], 'consent' => null, 'phone' => '12']))
        ->assertSessionHasErrors(['skills', 'phone']);
    expect(fn () => app(SaveVolunteer::class)->signUp(volunteerAnswers(['consent' => null]), 'en'))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveVolunteer::class)->signUp(volunteerAnswers(['skills' => ['juggling']]), 'en'))->toThrow(ValidationException::class);
    $this->post('/en/volunteer', volunteerAnswers([SpamGuard::HONEYPOT => 'http://spam.example']))->assertSessionHasErrors();
    expect(Volunteer::query()->count())->toBe(0);

    $this->get('/en/get-involved')->assertOk()->assertSee(route('volunteer.create', ['locale' => 'en']), escape: false);
});

it('lists the volunteers of a coordinator\'s woredas masked and filtered by what they offer', function () {
    $north = volunteerWoreda('Sample Woreda North');
    $mine = sampleVolunteer($north, ['phone' => '0911000101', 'skills' => ['driving']]);
    $alsoMine = sampleVolunteer($north, ['skills' => ['writing']]);
    $other = sampleVolunteer(volunteerWoreda('Sample Woreda South'));
    $this->actingAs(coordinatorFor($north));

    expect(VolunteerResource::getEloquentQuery()->pluck('id')->sort()->values()->all())->toBe(collect([$mine->id, $alsoMine->id])->sort()->values()->all());
    $this->get(VolunteerResource::getUrl('index'))->assertOk()->assertSee('Sample V. P.')->assertDontSee('Sample Volunteer Person')->assertDontSee('+251911000101');
    $this->get(VolunteerResource::getUrl('view', ['record' => $mine]))->assertOk()->assertSee('Sample Volunteer Person')->assertSee('school events');
    $this->get(VolunteerResource::getUrl('view', ['record' => $other]))->assertNotFound();

    livewire(ListVolunteers::class)->filterTable('skill', 'driving')->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$alsoMine]);
    $this->actingAs(staff(SystemRole::Editor))->get(VolunteerResource::getUrl('index'))->assertForbidden();
});

it('plans tasks at events and on their own, counts the hours given, and makes a new volunteer active', function () {
    $north = volunteerWoreda('Sample Woreda North');
    $volunteer = sampleVolunteer($north);
    $coordinator = coordinatorFor($north);
    $event = app(SaveEvent::class)->handle(new Event, [
        'starts_at' => CarbonImmutable::now()->addDays(4)->setTime(9, 0)->toDateTimeString(),
        'translations' => ['en' => ['title' => 'Sample volunteer forum', 'venue' => 'Sample hall', 'published' => true]],
    ], staff(SystemRole::Publisher));

    $atEvent = app(ManageVolunteer::class)->saveAssignment($volunteer, null, ['event_id' => $event->id, 'status' => 'planned'], $coordinator);
    expect($atEvent->task)->toBe('Sample volunteer forum')
        ->and($atEvent->on_date?->toDateString())->toBe($event->starts_at->toDateString())
        ->and($volunteer->refresh()->status)->toBe(VolunteerStatus::Active);

    app(ManageVolunteer::class)->saveAssignment($volunteer, $atEvent, ['event_id' => $event->id, 'task' => 'Ushering at the entrance', 'status' => 'done', 'hours' => '3.5'], $coordinator);
    app(ManageVolunteer::class)->saveAssignment($volunteer, null, ['task' => 'Sample leaflet folding', 'on_date' => '2026-10-01', 'status' => 'done', 'hours' => '2'], $coordinator);
    app(ManageVolunteer::class)->saveAssignment($volunteer, null, ['task' => 'Sample cancelled task', 'status' => 'cancelled', 'hours' => '9'], $coordinator);
    expect(ManageVolunteer::hours($volunteer))->toBe(5.5)
        ->and(fn () => app(ManageVolunteer::class)->saveAssignment($volunteer, null, ['task' => '', 'status' => 'planned'], $coordinator))->toThrow(ValidationException::class)
        ->and(fn () => app(ManageVolunteer::class)->saveAssignment($volunteer, null, ['task' => 'x', 'hours' => '-1'], $coordinator))->toThrow(ValidationException::class);

    $this->actingAs($coordinator);
    livewire(AssignmentsRelationManager::class, ['ownerRecord' => $volunteer, 'pageClass' => ViewVolunteer::class])
        ->assertCanSeeTableRecords($volunteer->assignments()->get())
        ->callTableAction('assign', data: ['task' => 'Sample task from the screen', 'status' => AssignmentStatus::Planned->value])
        ->assertHasNoTableActionErrors();
    expect($volunteer->assignments()->count())->toBe(4)
        ->and(AssignmentsRelationManager::getBadge($volunteer, ViewVolunteer::class))->toBe(__('volunteers.assignment.hours_badge', ['hours' => '5.5']));

    $outsider = coordinatorFor(volunteerWoreda('Sample Woreda South'));
    expect(fn () => app(ManageVolunteer::class)->saveAssignment($volunteer, null, ['task' => 'x'], $outsider))->toThrow(HttpException::class);
});

it('keeps a history of status changes, notes and corrected details', function () {
    $north = volunteerWoreda('Sample Woreda North');
    $volunteer = sampleVolunteer($north);
    $coordinator = coordinatorFor($north);
    $this->actingAs($coordinator);

    livewire(ViewVolunteer::class, ['record' => $volunteer->getRouteKey()])
        ->callAction('status', ['status' => VolunteerStatus::Inactive->value, 'reason' => 'Sample: moved away'])->assertHasNoActionErrors()
        ->callAction('note', ['body' => 'Sample note on the volunteer']);
    livewire(EditVolunteer::class, ['record' => $volunteer->getRouteKey()])
        ->assertSet('data.name', 'Sample Volunteer Person')
        ->fillForm(['skills' => ['writing'], 'name' => 'Sample Volunteer Renamed'])->call('save')->assertHasNoFormErrors();

    $volunteer->refresh();
    expect($volunteer->status)->toBe(VolunteerStatus::Inactive)
        ->and($volunteer->skills)->toBe(['writing'])
        ->and($volunteer->notes()->pluck('kind')->all())->toBe([PersonNote::UPDATE, PersonNote::NOTE, PersonNote::STATUS])
        ->and($volunteer->notes()->first()->body)->toBe(__('volunteers.changed.name').', '.__('volunteers.changed.skills'))
        ->and($volunteer->notes()->where('kind', PersonNote::STATUS)->value('body'))->toContain('Sample: moved away');
});

it('finds, copies and erases volunteer sign-ups in privacy requests, and deletes sign-ups nobody followed up', function () {
    $north = volunteerWoreda('Sample Woreda North');
    $volunteer = sampleVolunteer($north, ['phone' => '0911424242', 'email' => 'volunteer@example.org']);
    app(ManageVolunteer::class)->saveAssignment($volunteer, null, ['task' => 'Sample task', 'status' => 'done', 'hours' => '4'], staff(SystemRole::Admin));
    app(ManageVolunteer::class)->note($volunteer, 'Sample note naming the volunteer', staff(SystemRole::Admin));

    $search = new PersonalDataSearch('volunteer@example.org', null);
    expect($search->counts())->toMatchArray(['volunteers' => 1]);
    $export = app(ExportPersonalData::class)->handle($search, staff(SystemRole::Admin));
    expect($export['volunteering'][0])->toMatchArray(['name' => 'Sample Volunteer Person', 'phone' => '+251911424242', 'skills' => ['ushering', 'first_aid']])
        ->and($export['volunteering'][0]['assignments'][0])->toMatchArray(['task' => 'Sample task', 'hours' => '4.00']);

    app(ErasePersonalData::class)->handle(new PersonalDataSearch(null, '0911424242'), staff(SystemRole::Admin));
    $volunteer->refresh();
    expect($volunteer->name)->toBe(ErasePersonalData::ERASED)
        ->and([$volunteer->phone, $volunteer->email, $volunteer->message, $volunteer->phone_hash])->toBe([null, null, null, null])
        ->and($volunteer->status)->toBe(VolunteerStatus::Inactive)
        ->and(ManageVolunteer::hours($volunteer))->toBe(4.0)
        ->and($volunteer->notes()->where('kind', PersonNote::NOTE)->value('body'))->toBe(ErasePersonalData::ERASED)
        ->and((new PersonalDataSearch(null, '0911424242'))->erasable())->toBe(0);

    $old = sampleVolunteer($north);
    $old->forceFill(['created_at' => now()->subDays(400)])->save();
    $followed = sampleVolunteer($north);
    $followed->forceFill(['created_at' => now()->subDays(400), 'status' => VolunteerStatus::Active])->save();
    $this->artisan('membership:purge-applications')->assertSuccessful();
    expect(Volunteer::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(Volunteer::query()->whereKey($followed->id)->exists())->toBeTrue();
});
