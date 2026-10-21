<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Events\Actions\RegisterForEvent;
use App\Domain\Events\Actions\SaveEvent;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Forms\Actions\HandleSubmission;
use App\Domain\Forms\Actions\SaveForm;
use App\Domain\Forms\Actions\SubmitForm;
use App\Domain\Forms\Enums\SubmissionStatus;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Forms\Support\FormField;
use App\Domain\Privacy\Actions\ErasePersonalData;
use App\Domain\Privacy\Actions\ExportPersonalData;
use App\Domain\Privacy\Models\PrivacyRequest;
use App\Domain\Privacy\Support\PersonalDataSearch;
use App\Filament\Pages\PrivacyRequests;
use App\Services\Sms\SendSms;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

function privacyEvent(): Event
{
    return app(SaveEvent::class)->handle(new Event, [
        'starts_at' => CarbonImmutable::now()->addDays(5)->setTime(9, 0)->toDateTimeString(),
        'registration_enabled' => true,
        'translations' => ['en' => ['title' => 'Sample privacy forum', 'venue' => 'Sample hall', 'published' => true]],
    ], staff(SystemRole::Publisher));
}

function privacyForm(): Form
{
    return app(SaveForm::class)->handle(new Form, [
        'type' => 'feedback',
        'response_days' => 3,
        'translations' => ['en' => ['title' => 'Sample privacy form', 'success_message' => 'Sample thanks', 'published' => true]],
        'fields' => [['key' => 'msgfield', 'type' => FormField::TEXTAREA, 'required' => true, 'label' => ['en' => 'Your message']]],
    ], staff(SystemRole::Publisher));
}

/** One person who registered and wrote, and someone else who did too. */
function privacyPeople(): array
{
    $event = privacyEvent();
    $form = privacyForm();
    $register = fn (string $name, string $email, string $phone) => app(RegisterForEvent::class)->handle($event, ['name' => $name, 'email' => $email, 'phone' => $phone, 'consent' => true], 'en')->registration;
    $write = fn (string $name, string $email, string $phone, string $text) => app(SubmitForm::class)->handle($form, ['name' => $name, 'email' => $email, 'phone' => $phone, 'answers' => ['msgfield' => $text]], [], 'en');

    return [
        'registration' => $register('Sample Person', 'person@example.org', '0911234567'),
        'ticket' => $write('Sample Person', 'Person@Example.org', '', 'Sample private message'),
        'phoneTicket' => $write('Sample Person', '', '+251 911 23 45 67', 'Sample second message'),
        'other' => $register('Sample Other', 'other@example.org', '0922000000'),
        'otherTicket' => $write('Sample Other', 'other@example.org', '', 'Sample other message'),
    ];
}

beforeEach(function () {
    Mail::fake();
    Bus::fake([SendSms::class]);
});

it('finds a person\'s registrations and tickets by email or phone', function () {
    $people = privacyPeople();

    $byEmail = new PersonalDataSearch(' PERSON@example.org ', null);
    $byPhone = new PersonalDataSearch(null, '0911 23 45 67');

    expect($byEmail->registrations()->pluck('id')->all())->toBe([$people['registration']->id])
        ->and($byEmail->submissions()->pluck('id')->all())->toBe([$people['ticket']->id])
        ->and($byPhone->registrations()->pluck('id')->all())->toBe([$people['registration']->id])
        ->and($byPhone->submissions()->pluck('id')->all())->toBe([$people['phoneTicket']->id])
        ->and((new PersonalDataSearch('person@example.org', '0911234567'))->counts())
        ->toMatchArray(['registrations' => 1, 'submissions' => 2])
        ->and((new PersonalDataSearch('not an email', 'abc'))->isValid())->toBeFalse()
        ->and($people['ticket']->email_hash)->toBe(EventRegistration::hash('person@example.org'));
});

it('gives the person a copy of everything found and logs the request without their details', function () {
    $people = privacyPeople();
    app(HandleSubmission::class)->reply($people['ticket'], 'Sample reply to the person', staff(SystemRole::SupportAgent));
    $admin = staff(SystemRole::Admin);

    $export = app(ExportPersonalData::class)->handle(new PersonalDataSearch('person@example.org', '0911234567'), $admin);

    expect($export['event_registrations'])->toHaveCount(1)
        ->and($export['event_registrations'][0])->toMatchArray(['event' => 'Sample privacy forum', 'name' => 'Sample Person', 'phone' => '+251911234567'])
        ->and($export['messages_and_tickets'])->toHaveCount(2)
        ->and(json_encode($export, JSON_UNESCAPED_UNICODE))->toContain('Sample private message', 'Sample reply to the person')->not->toContain('Sample Other');

    $logged = PrivacyRequest::query()->sole();
    expect($logged->kind)->toBe(PrivacyRequest::EXPORT)
        ->and($logged->found)->toMatchArray(['registrations' => 1, 'submissions' => 2])
        ->and(json_encode($logged->toArray()))->not->toContain('person@example.org')->not->toContain('251911234567')
        ->and(Activity::query()->where('log_name', 'privacy')->where('event', 'export')->exists())->toBeTrue();
});

it('erases a person\'s data but keeps anonymous records that still add up', function () {
    Storage::fake(SubmitForm::FILE_DISK);
    $people = privacyPeople();
    Storage::disk(SubmitForm::FILE_DISK)->put("form-submissions/{$people['ticket']->id}/sample.pdf", 'sample');
    $people['ticket']->forceFill(['files' => [['field' => 'msgfield', 'path' => "form-submissions/{$people['ticket']->id}/sample.pdf", 'name' => 'sample.pdf', 'size' => 6, 'mime' => 'application/pdf']]])->save();
    app(HandleSubmission::class)->note($people['ticket'], 'Sample note naming the person', staff(SystemRole::SupportAgent));
    activity('auth')->event('login_failed')->withProperties(['email' => 'person@example.org'])->log('login_failed');
    $oldTicketUrl = route('events.ticket', ['token' => $people['registration']->token, 'locale' => 'en']);

    $found = app(ErasePersonalData::class)->handle(new PersonalDataSearch('person@example.org', '0911234567'), staff(SystemRole::Admin));

    $registration = $people['registration']->refresh();
    $ticket = $people['ticket']->refresh();
    expect($found)->toMatchArray(['registrations' => 1, 'submissions' => 2, 'failed_sign_ins' => 1])
        ->and($registration->name)->toBe(ErasePersonalData::ERASED)
        ->and([$registration->email, $registration->phone, $registration->email_hash])->toBe([null, null, null])
        ->and($registration->erased_at)->not->toBeNull()
        ->and([$ticket->name, $ticket->email, $ticket->phone, $ticket->answers, $ticket->files])->toBe([null, null, null, [], null])
        ->and($ticket->notes()->where('kind', 'note')->value('body'))->toBe(ErasePersonalData::ERASED)
        ->and($ticket->reference)->not->toBeEmpty()
        ->and(DB::table('event_registrations')->where('event_id', $registration->event_id)->count())->toBe(2)
        ->and((new PersonalDataSearch('person@example.org', '0911234567'))->counts())->toMatchArray(['registrations' => 0, 'submissions' => 0, 'failed_sign_ins' => 0])
        ->and($people['other']->refresh()->name)->toBe('Sample Other')
        ->and(PrivacyRequest::query()->where('kind', PrivacyRequest::ERASE)->value('found'))->toMatchArray(['registrations' => 1]);
    Storage::disk(SubmitForm::FILE_DISK)->assertMissing("form-submissions/{$ticket->id}/sample.pdf");

    $this->get($oldTicketUrl)->assertNotFound();
});

it('deletes tickets resolved longer ago than the retention period, never open ones', function () {
    Storage::fake(SubmitForm::FILE_DISK);
    $form = privacyForm();
    $old = app(SubmitForm::class)->handle($form, ['name' => 'Sample Old', 'email' => 'old@example.org', 'answers' => ['msgfield' => 'Old']], [], 'en');
    $open = app(SubmitForm::class)->handle($form, ['name' => 'Sample Open', 'email' => 'open@example.org', 'answers' => ['msgfield' => 'Open']], [], 'en');
    $recent = app(SubmitForm::class)->handle($form, ['name' => 'Sample Recent', 'email' => 'recent@example.org', 'answers' => ['msgfield' => 'Recent']], [], 'en');
    $old->forceFill(['status' => SubmissionStatus::Resolved, 'resolved_at' => now()->subYears(3)])->save();
    $open->forceFill(['created_at' => now()->subYears(3)])->save();
    $recent->forceFill(['status' => SubmissionStatus::Resolved, 'resolved_at' => now()->subMonth()])->save();
    Storage::disk(SubmitForm::FILE_DISK)->put("form-submissions/{$old->id}/sample.pdf", 'sample');

    $this->artisan('forms:purge-submissions')->assertSuccessful();

    expect(FormSubmission::query()->pluck('id')->sort()->values()->all())->toBe(collect([$open->id, $recent->id])->sort()->values()->all());
    Storage::disk(SubmitForm::FILE_DISK)->assertMissing("form-submissions/{$old->id}/sample.pdf");
});

it('removes notices read long ago and any older than a year', function () {
    $user = staff(SystemRole::Editor);
    $row = fn (string $id, ?CarbonImmutable $readAt, CarbonImmutable $createdAt): array => ['id' => $id, 'type' => 'sample', 'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->id, 'data' => '{}', 'read_at' => $readAt, 'created_at' => $createdAt, 'updated_at' => $createdAt];
    DB::table('notifications')->insert([
        $row((string) Str::uuid(), CarbonImmutable::now()->subDays(100), CarbonImmutable::now()->subDays(120)),
        $row((string) Str::uuid(), null, CarbonImmutable::now()->subDays(400)),
        $keep = $row((string) Str::uuid(), null, CarbonImmutable::now()->subDays(30)),
    ]);

    $this->artisan('notifications:prune')->assertSuccessful();

    expect(DB::table('notifications')->pluck('id')->all())->toBe([$keep['id']]);
});

it('lets administrators search, export and erase from the privacy page', function () {
    privacyPeople();
    $this->actingAs(staff(SystemRole::Admin));

    livewire(PrivacyRequests::class)
        ->assertOk()
        ->assertActionHidden('export')
        ->fillForm(['email' => 'person@example.org'])
        ->call('find')
        ->assertSee(__('privacy.found.registrations'))
        ->assertActionVisible('erase')
        ->callAction('export')
        ->assertFileDownloaded('personal-data-'.now()->format('Y-m-d').'.json')
        ->callAction('erase')
        ->assertNotified(__('privacy.erased', ['registrations' => 1, 'submissions' => 1, 'applications' => 0, 'members' => 0, 'volunteers' => 0, 'subscribers' => 0]));

    expect(PrivacyRequest::query()->count())->toBe(2);
});

it('is only for staff who handle privacy requests', function () {
    $this->actingAs(staff(SystemRole::Editor))->get(PrivacyRequests::getUrl())->assertForbidden();
    $this->actingAs(staff(SystemRole::Admin))->get(PrivacyRequests::getUrl())->assertOk();
});
