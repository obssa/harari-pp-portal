<?php

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Enums\SystemRole;
use App\Domain\Access\Enums\UserStatus;
use App\Domain\Content\Notifications\WorkflowNotification;
use App\Domain\Events\Actions\RegisterForEvent;
use App\Domain\Events\Actions\SaveEvent;
use App\Domain\Events\Models\Event;
use App\Domain\Forms\Actions\HandleSubmission;
use App\Domain\Forms\Actions\SaveForm;
use App\Domain\Forms\Actions\SubmitForm;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Forms\Notifications\NewTicketNotice;
use App\Domain\Forms\Notifications\TicketAssignedNotice;
use App\Domain\Forms\Support\FormField;
use App\Domain\Import\Actions\RunImport;
use App\Domain\Import\Notifications\StoriesImportedNotice;
use App\Domain\Notifications\Channels\GroupedDatabaseChannel;
use App\Domain\Notifications\Enums\NoticeKind;
use App\Domain\Notifications\Notifications\SourceFailingNotice;
use App\Domain\Notifications\Support\NoticePreferences;
use App\Domain\Organisation\Actions\SaveOffice;
use App\Domain\Organisation\Models\Office;
use App\Domain\Social\Actions\FetchSocialSource;
use App\Domain\Social\Enums\Platform;
use App\Domain\Social\Models\SocialSource;
use App\Domain\Translation\Jobs\TranslateMissingStrings;
use App\Domain\Translation\Notifications\MachineTranslationNotice;
use App\Models\User;
use App\Services\Sms\SendSms;
use App\Settings\IntegrationSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

function noticeOffice(string $name): Office
{
    return app(SaveOffice::class)->handle(new Office, ['type' => 'woreda', 'translations' => ['en' => ['name' => $name, 'published' => true]]], staff(SystemRole::Publisher));
}

function noticeForm(?Office $office = null): Form
{
    return app(SaveForm::class)->handle(new Form, [
        'type' => 'feedback',
        'response_days' => 3,
        'office_id' => $office?->id,
        'translations' => ['en' => ['title' => 'Sample notice form', 'success_message' => 'Sample thanks', 'published' => true]],
        'fields' => [['key' => 'msgfield', 'type' => FormField::TEXTAREA, 'required' => true, 'label' => ['en' => 'Your message']]],
    ], staff(SystemRole::Publisher));
}

function noticeTicket(Form $form): FormSubmission
{
    return app(SubmitForm::class)->handle($form, [
        'name' => 'Sample Writer', 'email' => 'writer@example.org', 'answers' => ['msgfield' => 'Sample message'],
    ], [], 'en');
}

function officeAgent(Office $office): User
{
    $agent = staff(SystemRole::SupportAgent);
    $agent->assignScopedRole(SystemRole::SupportAgent, ScopeType::Office, $office->id);

    return $agent->refresh();
}

function noticeEvent(int $capacity): Event
{
    return app(SaveEvent::class)->handle(new Event, [
        'starts_at' => CarbonImmutable::now()->addDays(5)->setTime(9, 0)->toDateTimeString(),
        'registration_enabled' => true,
        'capacity' => $capacity,
        'waitlist' => true,
        'translations' => ['en' => ['title' => 'Sample notice forum', 'venue' => 'Sample hall', 'published' => true]],
    ], staff(SystemRole::Publisher));
}

/** @return array<string, mixed> */
function storedNotice(User $user): array
{
    return (array) $user->notifications()->firstOrFail()->data;
}

beforeEach(function () {
    Mail::fake();
    Bus::fake([SendSms::class]);
});

it('keeps the defaults until a person chooses, and offers only the kinds of their work', function () {
    $agent = staff(SystemRole::SupportAgent);

    expect(NoticeKind::for($agent))->toContain(NoticeKind::Tickets)->not->toContain(NoticeKind::Registrations)
        ->and(NoticePreferences::channels($agent, NoticeKind::Tickets))->toBe([NoticePreferences::IN_APP, NoticePreferences::EMAIL])
        ->and(NoticePreferences::channels($agent, NoticeKind::Registrations))->toBe([NoticePreferences::IN_APP])
        ->and(NoticePreferences::via($agent, NoticeKind::Tickets))->toBe([GroupedDatabaseChannel::class, 'mail']);

    NoticePreferences::save($agent, ['tickets' => ['email', 'sms'], 'registrations' => ['email']]);
    $agent->refresh();

    expect(NoticePreferences::channels($agent, NoticeKind::Tickets))->toBe([NoticePreferences::EMAIL])
        ->and($agent->notification_preferences)->not->toHaveKey('registrations')
        ->and(NoticePreferences::via($agent, NoticeKind::Tickets))->toBe(['mail']);

    NoticePreferences::save($agent, ['tickets' => []]);
    expect(NoticePreferences::via($agent->refresh(), NoticeKind::Tickets))->toBe([]);
});

it('sends post workflow notices only where the person wants them', function () {
    Notification::fake();
    $editor = staff(SystemRole::Editor);
    NoticePreferences::save($editor, ['workflow' => ['in_app']]);

    $notice = new WorkflowNotification('01post', 'Sample post', 'en', 'submit', 'Sample Author', null);
    Notification::send($editor->refresh(), $notice);

    Notification::assertSentTo($editor, WorkflowNotification::class, fn ($n, array $channels): bool => $channels === [GroupedDatabaseChannel::class]);
});

it('tells the staff of the office about a new ticket, and nobody outside it', function () {
    Notification::fake();
    $north = noticeOffice('Sample North office');
    $south = noticeOffice('Sample South office');
    $northAgent = officeAgent($north);
    $southAgent = officeAgent($south);
    $everyOffice = staff(SystemRole::SupportAgent);
    $suspended = staff(SystemRole::SupportAgent, attributes: ['status' => UserStatus::Suspended]);

    $ticket = noticeTicket(noticeForm($north));

    Notification::assertSentTo([$northAgent, $everyOffice], NewTicketNotice::class);
    Notification::assertNotSentTo([$southAgent, $suspended], NewTicketNotice::class);

    $notice = NewTicketNotice::for($ticket);
    $stored = json_encode($notice->toDatabase($northAgent), JSON_UNESCAPED_UNICODE);
    expect($stored)->toContain('Sample notice form')->toContain($ticket->reference)->toContain('Sample North office')
        ->not->toContain('Sample Writer')->not->toContain('writer@example.org');
});

it('tells a person when a colleague gives them a ticket, not when they take it themselves', function () {
    Notification::fake();
    $ticket = noticeTicket(noticeForm());
    $lead = staff(SystemRole::SupportAgent, attributes: ['name' => 'Sample Lead']);
    $colleague = staff(SystemRole::SupportAgent);

    app(HandleSubmission::class)->assign($ticket, $colleague, $lead);
    app(HandleSubmission::class)->assign($ticket->refresh(), $lead, $lead);

    Notification::assertSentTo($colleague, TicketAssignedNotice::class, fn (TicketAssignedNotice $n): bool => $n->actorName === 'Sample Lead');
    Notification::assertNotSentTo($lead, TicketAssignedNotice::class);
});

it('keeps one up-to-date notice per event until it is read', function () {
    $officer = staff(SystemRole::MembershipOfficer);
    $event = noticeEvent(capacity: 2);
    $register = fn (string $email) => app(RegisterForEvent::class)->handle($event, ['name' => 'Sample Person', 'email' => $email, 'consent' => true], 'en');

    $register('one@example.org');
    $register('two@example.org');
    $register('two@example.org');

    expect($officer->notifications()->count())->toBe(1)
        ->and(storedNotice($officer)['title'])->toBe('The event is full: Sample notice forum')
        ->and(storedNotice($officer)['body'])->toBe('Registered: 2. Places left: 0.')
        ->and(storedNotice($officer)['group'])->toBe("registrations:{$event->id}");

    $officer->unreadNotifications()->update(['read_at' => now()]);
    $register('three@example.org');

    expect($officer->notifications()->count())->toBe(2)
        ->and($officer->unreadNotifications()->firstOrFail()->data['body'])->toBe('Registered: 2. Places left: 0. On the waiting list: 1.');
});

it('reports a failing social account once, not on every attempt', function () {
    Notification::fake();
    $manager = staff(SystemRole::MediaManager);
    $author = staff(SystemRole::Author);
    $source = SocialSource::query()->create(['platform' => Platform::Telegram, 'handle' => 'samplenotices', 'name' => 'Sample notices channel']);
    Http::fake(['t.me/*' => Http::response('down', 502)]);

    app(FetchSocialSource::class)->handle($source);
    app(FetchSocialSource::class)->handle($source->refresh());

    Notification::assertSentToTimes($manager, SourceFailingNotice::class, 1);
    Notification::assertNotSentTo($author, SourceFailingNotice::class);

    $source->forceFill(['last_error' => null])->save();
    app(FetchSocialSource::class)->handle($source->refresh());
    Notification::assertSentToTimes($manager, SourceFailingNotice::class, 2);
});

it('tells reviewers about new drafts and administrators about a failing import channel', function () {
    Notification::fake();
    $editor = staff(SystemRole::Editor);
    $admin = staff(SystemRole::Admin);
    $page = '<html><body><section class="tgme_channel_history"><div class="tgme_widget_message_wrap js-widget_message_wrap">'
        .'<div class="tgme_widget_message js-widget_message" data-post="HarariGovernmentCommunication/7"><div class="tgme_widget_message_bubble">'
        .'<div class="tgme_widget_message_text js-message_text" dir="auto">Sample office note</div>'
        .'<div class="tgme_widget_message_footer"><a class="tgme_widget_message_date" href="#"><time datetime="2026-10-15T12:00:00+00:00" class="time">12:00</time></a></div>'
        .'</div></div></div></section></body></html>';
    Http::fake([
        't.me/s/hrprosperityparty*' => Http::response('down', 502),
        't.me/s/HarariGovernmentCommunication*' => Http::response($page),
    ]);

    app(RunImport::class)->handle();

    Notification::assertSentTo($editor, StoriesImportedNotice::class, fn (StoriesImportedNotice $n): bool => $n->count === 1);
    Notification::assertSentTo($admin, SourceFailingNotice::class, fn (SourceFailingNotice $n): bool => $n->type === SourceFailingNotice::IMPORT
        && str_contains($n->error, '502'));
    Notification::assertNotSentTo($editor, SourceFailingNotice::class);
});

it('tells the translator when the machine translation is done', function () {
    Notification::fake();
    $settings = app(IntegrationSettings::class);
    $settings->mt_driver = 'google';
    $settings->google_translate_api_key = 'sample-google-key';
    $settings->save();
    Http::fake(['translation.googleapis.com/*' => fn (Request $request) => Http::response(['data' => ['translations' => array_map(
        fn (string $q): array => ['translatedText' => 'OM '.$q], $request['q'],
    )]])]);
    $lang = sys_get_temp_dir().'/notice-lang-'.uniqid();
    mkdir($lang);
    file_put_contents("{$lang}/en.json", json_encode(['nav.home' => 'Home', 'nav.news' => 'News']));
    file_put_contents("{$lang}/om.json", json_encode(['nav.home' => '', 'nav.news' => '']));
    app()->useLangPath($lang);
    $translator = staff(SystemRole::Translator);
    $translator->assignScopedRole(SystemRole::Translator, ScopeType::Language, 'om');

    TranslateMissingStrings::dispatchSync('om', $translator->id);

    Notification::assertSentTo($translator, MachineTranslationNotice::class, fn (MachineTranslationNotice $n): bool => $n->translated === 2 && $n->missing === 0);
});

it('writes the notice in the person\'s admin language', function () {
    $agent = staff(SystemRole::SupportAgent, attributes: ['locale' => 'am']);
    $agent->notify(new StoriesImportedNotice(3));

    expect(storedNotice($agent)['title'])->toBe(__('notifications.import.title', [], 'am'))
        ->and(storedNotice($agent)['kind'])->toBe('imports');
});
