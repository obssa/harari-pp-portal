<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Events\Actions\CancelRegistration;
use App\Domain\Events\Actions\RegisterForEvent;
use App\Domain\Events\Actions\SaveEvent;
use App\Domain\Events\Actions\SendRegistrationMessage;
use App\Domain\Events\Enums\RegistrationStatus;
use App\Domain\Events\Mail\RegistrationMail;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Events\Support\RegistrationExport;
use App\Filament\Pages\CheckIn;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\RelationManagers\RegistrationsRelationManager;
use App\Services\Sms\LogSmsGateway;
use App\Services\Sms\SendSms;
use App\Settings\IntegrationSettings;
use App\Support\PhoneNumber;
use App\Support\SpamGuard;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

use function Pest\Livewire\livewire;

/**
 * @param  array<string, mixed>  $data
 */
function registrationEvent(array $data = [], string $title = 'Sample registration forum'): Event
{
    return app(SaveEvent::class)->handle(new Event, [
        'starts_at' => CarbonImmutable::now()->addDays(5)->setTime(9, 0)->toDateTimeString(),
        'registration_enabled' => true,
        'translations' => ['en' => ['title' => $title, 'venue' => 'Sample hall', 'published' => true]],
        ...$data,
    ], staff(SystemRole::Publisher));
}

/**
 * @param  array<string, mixed>  $data
 */
function register(Event $event, array $data): EventRegistration
{
    return app(RegisterForEvent::class)->handle($event, [...$data, 'consent' => true], 'en')->registration;
}

/** A form opened ten seconds ago, as a person would send it. */
function formStamp(): string
{
    return Crypt::encryptString((string) (time() - 10));
}

beforeEach(function () {
    Mail::fake();
    Bus::fake([SendSms::class]);
});

it('writes Ethiopian and international phone numbers one way', function (string $typed, ?string $stored) {
    expect(PhoneNumber::normalise($typed))->toBe($stored);
})->with([
    ['0911 23 45 67', '+251911234567'],
    ['911234567', '+251911234567'],
    ['0711-234-567', '+251711234567'],
    ['251911234567', '+251911234567'],
    ['+251 91 123 4567', '+251911234567'],
    ['00251911234567', '+251911234567'],
    ['+44 20 7946 0958', '+442079460958'],
    ['0111234567', null],
    ['12345', null],
    ['', null],
]);

it('gives places in order, then the waiting list, and stays closed when full without one', function () {
    $event = registrationEvent(['capacity' => 2]);

    $first = register($event, ['name' => 'Sample One', 'phone' => '0911000001']);
    $second = register($event, ['name' => 'Sample Two', 'email' => 'two@example.org']);
    $third = register($event, ['name' => 'Sample Three', 'email' => 'three@example.org']);

    expect([$first->status, $second->status, $third->status])->toBe([RegistrationStatus::Registered, RegistrationStatus::Registered, RegistrationStatus::Waitlisted])
        ->and($third->waitlistPosition())->toBe(1)
        ->and($event->refresh()->registrationState())->toBe(Event::REGISTRATION_WAITLIST)
        ->and($first->phone)->toBe('+251911000001');

    // Personal details and the ticket code are encrypted at rest.
    $raw = DB::table('event_registrations')->where('id', $first->id)->first();
    expect($raw->name)->not->toContain('Sample One')
        ->and($raw->phone)->not->toContain('911000001')
        ->and($raw->token)->not->toBe($first->token)
        ->and($raw->token_hash)->toBe(EventRegistration::hash($first->token));

    Mail::assertQueued(RegistrationMail::class, fn (RegistrationMail $mail): bool => $mail->kind === SendRegistrationMessage::CONFIRMED && $mail->hasTo('two@example.org'));
    Mail::assertQueued(RegistrationMail::class, fn (RegistrationMail $mail): bool => $mail->kind === SendRegistrationMessage::WAITLISTED && $mail->hasTo('three@example.org'));
    Bus::assertDispatched(SendSms::class, fn (SendSms $sms): bool => $sms->to === '+251911000001' && str_contains($sms->text, '/en/events/ticket/'));

    $small = registrationEvent(['capacity' => 1, 'waitlist' => false], 'Sample small meeting');
    register($small, ['name' => 'Sample Four', 'email' => 'four@example.org']);
    expect($small->refresh()->registrationState())->toBe(Event::REGISTRATION_FULL)
        ->and(fn () => register($small, ['name' => 'Sample Five', 'email' => 'five@example.org']))->toThrow(ValidationException::class);
});

it('asks for a name, a way to reach the person and their agreement', function (array $data) {
    expect(fn () => app(RegisterForEvent::class)->handle(registrationEvent(), $data, 'en'))->toThrow(ValidationException::class);
})->with([
    'no name' => [['name' => ' ', 'email' => 'a@example.org', 'consent' => true]],
    'no contact' => [['name' => 'Sample', 'consent' => true]],
    'bad phone' => [['name' => 'Sample', 'phone' => '12', 'consent' => true]],
    'bad email' => [['name' => 'Sample', 'email' => 'not-an-address', 'consent' => true]],
    'no consent' => [['name' => 'Sample', 'email' => 'a@example.org']],
]);

it('registers the same phone or email once and sends the ticket again', function () {
    $event = registrationEvent();
    $first = register($event, ['name' => 'Sample Person', 'email' => 'person@example.org', 'phone' => '0911000002']);

    $again = app(RegisterForEvent::class)->handle($event, ['name' => 'Someone Else', 'email' => 'PERSON@example.org', 'consent' => true], 'en');
    $byPhone = app(RegisterForEvent::class)->handle($event, ['name' => 'Someone Else', 'phone' => '+251 911 000 002', 'consent' => true], 'en');

    expect($again->existing)->toBeTrue()->and($again->registration->id)->toBe($first->id)
        ->and($byPhone->registration->id)->toBe($first->id)
        ->and($event->registrations()->count())->toBe(1);
    Mail::assertQueued(RegistrationMail::class, fn (RegistrationMail $mail): bool => $mail->kind === SendRegistrationMessage::AGAIN && $mail->hasTo('person@example.org'));
});

it('moves the waiting list up when a place is given back or places are added', function () {
    $event = registrationEvent(['capacity' => 1]);
    $first = register($event, ['name' => 'Sample First', 'email' => 'first@example.org']);
    $waiting = register($event, ['name' => 'Sample Waiting', 'email' => 'waiting@example.org']);
    $later = register($event, ['name' => 'Sample Later', 'email' => 'later@example.org']);

    app(CancelRegistration::class)->handle($first);

    expect($first->refresh()->status)->toBe(RegistrationStatus::Cancelled)
        ->and($waiting->refresh()->status)->toBe(RegistrationStatus::Registered)
        ->and($waiting->promoted_at)->not->toBeNull()
        ->and($later->refresh()->status)->toBe(RegistrationStatus::Waitlisted);
    Mail::assertQueued(RegistrationMail::class, fn (RegistrationMail $mail): bool => $mail->kind === SendRegistrationMessage::PROMOTED && $mail->hasTo('waiting@example.org'));
    Mail::assertQueued(RegistrationMail::class, fn (RegistrationMail $mail): bool => $mail->kind === SendRegistrationMessage::CANCELLED && $mail->hasTo('first@example.org'));

    app(SaveEvent::class)->handle($event->refresh(), [
        'starts_at' => $event->starts_at->toDateTimeString(),
        'registration_enabled' => true,
        'capacity' => 5,
        'translations' => ['en' => ['title' => 'Sample registration forum', 'published' => true]],
    ], staff(SystemRole::Publisher));

    expect($later->refresh()->status)->toBe(RegistrationStatus::Registered);
});

it('registers from the public form and shows the ticket with its QR code', function () {
    $event = registrationEvent(['capacity' => 10]);

    $this->get('/en/events/sample-registration-forum')
        ->assertSee(route('events.register', ['locale' => 'en', 'slug' => 'sample-registration-forum']), escape: false);

    $this->get('/en/events/sample-registration-forum/register')
        ->assertOk()
        ->assertSee('name="'.SpamGuard::HONEYPOT.'"', escape: false)
        ->assertSee(trans_choice('events.registration.places_left', 10, ['count' => 10], 'en'))
        ->assertDontSee('challenges.cloudflare.com', escape: false);

    $response = $this->post('/en/events/sample-registration-forum/register', [
        'name' => 'Sample Visitor', 'phone' => '0911 000 003', 'consent' => '1', SpamGuard::STARTED => formStamp(),
    ]);
    $registration = EventRegistration::query()->firstOrFail();
    $response->assertRedirect(route('events.ticket', ['locale' => 'en', 'token' => $registration->token]));

    $this->get(route('events.ticket', ['locale' => 'en', 'token' => $registration->token]))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('Sample Visitor')
        ->assertSee('data:image/svg+xml;base64,', escape: false)
        ->assertSee(__('events.ticket.cancel', [], 'en'));
    expect($registration->event_id)->toBe($event->id);

    $this->get('/en/events/ticket/'.str_repeat('a', 40))->assertNotFound();
});

it('turns robots away: the hidden field, a form sent too fast, and a failed Turnstile check', function () {
    registrationEvent();
    $form = ['name' => 'Sample Robot', 'email' => 'robot@example.org', 'consent' => '1'];

    $this->post('/en/events/sample-registration-forum/register', [...$form, SpamGuard::STARTED => formStamp(), SpamGuard::HONEYPOT => 'http://spam.example'])
        ->assertSessionHasErrors('form');
    $this->post('/en/events/sample-registration-forum/register', [...$form, SpamGuard::STARTED => SpamGuard::stamp()])
        ->assertSessionHasErrors('form');
    $this->post('/en/events/sample-registration-forum/register', [...$form, SpamGuard::STARTED => 'forged'])
        ->assertSessionHasErrors('form');

    $settings = app(IntegrationSettings::class);
    $settings->turnstile_site_key = 'sample-site-key';
    $settings->turnstile_secret_key = 'sample-secret';
    $settings->save();
    $this->get('/en/events/sample-registration-forum/register')->assertSee('data-sitekey="sample-site-key"', escape: false);

    // Cloudflare says no to the first answer and yes to the second.
    Http::fake(['challenges.cloudflare.com/*' => Http::sequence()->push(['success' => false])->push(['success' => true])]);
    $this->post('/en/events/sample-registration-forum/register', [...$form, SpamGuard::STARTED => formStamp(), 'cf-turnstile-response' => 'token'])
        ->assertSessionHasErrors('form');
    expect(EventRegistration::query()->count())->toBe(0);

    $this->post('/en/events/sample-registration-forum/register', [...$form, SpamGuard::STARTED => formStamp(), 'cf-turnstile-response' => 'token'])
        ->assertSessionHasNoErrors();
    expect(EventRegistration::query()->count())->toBe(1);
    Http::assertSent(fn ($request): bool => $request['secret'] === 'sample-secret' && $request['response'] === 'token');
});

it('closes registration at its closing time and has no form for events without registration', function () {
    registrationEvent(['registration_closes_at' => CarbonImmutable::now()->subHour()->toDateTimeString()]);
    registrationEvent(['registration_enabled' => false], 'Sample open meeting');

    $this->get('/en/events/sample-registration-forum/register')->assertOk()
        ->assertSee(__('events.registration.closed', [], 'en'))
        ->assertDontSee('name="consent"', escape: false);
    $this->post('/en/events/sample-registration-forum/register', [
        'name' => 'Sample Late', 'email' => 'late@example.org', 'consent' => '1', SpamGuard::STARTED => formStamp(),
    ])->assertSessionHasErrors('form');

    $this->get('/en/events/sample-open-meeting/register')->assertNotFound();
    $this->get('/en/events/sample-open-meeting')->assertDontSee(__('events.registration.cta', [], 'en'));
});

it('lets people give their place back from the ticket', function () {
    $event = registrationEvent();
    $registration = register($event, ['name' => 'Sample Leaver', 'email' => 'leaver@example.org']);

    $this->post(route('events.ticket.cancel', ['locale' => 'en', 'token' => $registration->token]))
        ->assertRedirect(route('events.ticket', ['locale' => 'en', 'token' => $registration->token]));

    expect($registration->refresh()->status)->toBe(RegistrationStatus::Cancelled);
    $this->get(route('events.ticket', ['locale' => 'en', 'token' => $registration->token]))
        ->assertSee(__('events.ticket.cancelled', [], 'en'))
        ->assertDontSee('data:image/svg+xml', escape: false);
});

it('reminds people once in the day before the event', function () {
    $soon = registrationEvent(['starts_at' => CarbonImmutable::now()->addHours(20)->toDateTimeString()], 'Sample soon meeting');
    $later = registrationEvent(['starts_at' => CarbonImmutable::now()->addDays(3)->toDateTimeString()], 'Sample later meeting');
    $person = register($soon, ['name' => 'Sample Soon', 'email' => 'soon@example.org', 'phone' => '0911000004']);
    register($later, ['name' => 'Sample Later', 'email' => 'later@example.org']);

    $this->artisan('events:send-reminders')->expectsOutputToContain('Reminders sent: 1')->assertSuccessful();
    $this->artisan('events:send-reminders')->expectsOutputToContain('Reminders sent: 0')->assertSuccessful();

    expect($person->refresh()->reminded_at)->not->toBeNull();
    Mail::assertQueued(RegistrationMail::class, fn (RegistrationMail $mail): bool => $mail->kind === SendRegistrationMessage::REMINDER && $mail->hasTo('soon@example.org'));
    Mail::assertNotQueued(RegistrationMail::class, fn (RegistrationMail $mail): bool => $mail->kind === SendRegistrationMessage::REMINDER && $mail->hasTo('later@example.org'));
    Bus::assertDispatched(SendSms::class, fn (SendSms $sms): bool => str_starts_with($sms->text, 'Reminder: Sample soon meeting'));
});

it('writes the confirmation email in the language of the registration, with the calendar file', function () {
    $event = registrationEvent();
    $registration = register($event, ['name' => 'Sample Reader', 'email' => 'reader@example.org']);

    $mail = (new RegistrationMail($registration, SendRegistrationMessage::CONFIRMED))->locale('en');
    $mail->assertSeeInHtml('Sample Reader')
        ->assertSeeInHtml(route('events.ticket', ['locale' => 'en', 'token' => $registration->token]), escape: false)
        ->assertHasSubject(__('events.registration.mail.confirmed.subject', ['event' => 'Sample registration forum'], 'en'));
    expect($mail->attachments())->toHaveCount(1);
});

it('writes text messages to their own log with the number partly hidden, whatever the log level', function () {
    $path = storage_path('logs/sms-test-'.Str::random(8).'.log');
    config(['logging.default' => 'stack', 'logging.channels.stack' => ['driver' => 'stack', 'channels' => []], 'logging.level' => 'error',
        'logging.channels.sms' => [...config('logging.channels.sms'), 'driver' => 'single', 'path' => $path]]);

    (new SendSms('+251911234567', 'Sample text'))->handle(new LogSmsGateway);

    $written = (string) file_get_contents($path);
    @unlink($path);
    expect($written)->toContain('SMS not sent (log driver)')->toContain('+251••••••567')->toContain('Sample text')->not->toContain('911234567');
});

it('deletes registrations once the retention period after the event has passed', function () {
    $old = registrationEvent(['starts_at' => CarbonImmutable::now()->subDays(200)->toDateTimeString(), 'registration_enabled' => true], 'Sample old meeting');
    $recent = registrationEvent(['starts_at' => CarbonImmutable::now()->subDays(10)->toDateTimeString()], 'Sample recent meeting');
    // Registered while the events were still ahead.
    foreach ([$old, $recent] as $event) {
        $event->registrations()->create([
            'locale' => 'en', 'name' => 'Sample', 'email' => 'x@example.org', 'status' => RegistrationStatus::Registered,
            'token' => $token = Str::random(40), 'token_hash' => EventRegistration::hash($token),
        ]);
    }

    $this->artisan('events:purge-registrations')->expectsOutputToContain('Registrations deleted: 1')->assertSuccessful();

    expect($old->registrations()->count())->toBe(0)->and($recent->registrations()->count())->toBe(1);
});

it('lets registration staff check people in by QR code, and keeps others away from the details', function () {
    $event = registrationEvent();
    $registration = register($event, ['name' => 'Sample Guest', 'email' => 'guest@example.org']);

    $this->actingAs(staff(SystemRole::Editor))->get('/admin/check-in')->assertForbidden();
    expect(RegistrationsRelationManager::canViewForRecord($event, EditEvent::class))->toBeFalse();

    $officer = staff(SystemRole::MembershipOfficer);
    $this->actingAs($officer);
    $this->get('/admin/events/'.$event->getRouteKey())->assertOk();

    livewire(CheckIn::class, ['code' => route('filament.admin.pages.check-in', ['code' => $registration->token])])
        ->assertSee('Sample Guest')
        ->call('checkIn')
        ->assertNotified(__('events.check_in.done', ['name' => 'Sample Guest'], 'en'));
    expect($registration->refresh()->checked_in_at)->not->toBeNull()
        ->and($registration->checked_in_by)->toBe($officer->id);

    livewire(CheckIn::class, ['code' => $registration->token])->call('checkIn')->assertNotified();
    expect($registration->refresh()->checked_in_at)->not->toBeNull();
    livewire(CheckIn::class, ['code' => 'unknown-code'])->assertSee(__('events.check_in.unknown', [], 'en'));

    expect(RegistrationsRelationManager::canViewForRecord($event, EditEvent::class))->toBeTrue();
    $other = register($event, ['name' => 'Sample Second', 'email' => 'second@example.org']);
    livewire(RegistrationsRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class])
        ->assertCanSeeTableRecords([$registration, $other])
        ->callAction(TestAction::make('cancel')->table($other));
    expect($other->refresh()->status)->toBe(RegistrationStatus::Cancelled)
        ->and($registration->refresh()->status)->toBe(RegistrationStatus::Registered);
});

it('downloads the list as CSV with Ethiopic names intact and formulas disarmed', function () {
    $event = registrationEvent();
    register($event, ['name' => 'የናሙና ተሳታፊ', 'phone' => '0911000005']);
    register($event, ['name' => '=HYPERLINK("http://evil.example")', 'email' => 'formula@example.org']);

    $stream = fopen('php://memory', 'w+b');
    RegistrationExport::write($event, $stream);
    rewind($stream);
    $csv = (string) stream_get_contents($stream);

    expect($csv)->toStartWith("\u{FEFF}".__('events.registration.export.name', [], 'en'))
        ->toContain('የናሙና ተሳታፊ')
        ->toContain("'+251911000005")
        ->toContain("\"'=HYPERLINK(\"\"http://evil.example\"\")\"")
        ->not->toContain(',=HYPERLINK');
});

it('lets Turnstile load on public pages', function () {
    $policy = (string) $this->get('/en')->headers->get('Content-Security-Policy');

    expect($policy)->toContain("script-src 'self' 'nonce-")->toContain('https://challenges.cloudflare.com');
});
