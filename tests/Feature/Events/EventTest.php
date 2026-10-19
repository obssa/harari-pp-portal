<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Events\Actions\SaveEvent;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Support\IcsCalendar;
use App\Domain\Organisation\Actions\SaveLeader;
use App\Domain\Organisation\Models\Leader;
use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\EditEvent;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

use function Pest\Livewire\livewire;

/**
 * @param  array<string, array<string, mixed>>  $translations
 * @param  array<string, mixed>  $data
 */
function makeEvent(array $translations, string $starts = '+3 days 09:00', array $data = []): Event
{
    return app(SaveEvent::class)->handle(new Event, [
        'starts_at' => CarbonImmutable::parse($starts)->toDateTimeString(),
        'translations' => $translations,
        ...$data,
    ], staff(SystemRole::Publisher));
}

it('saves events with agenda, speakers and a map pin, and checks the times', function () {
    $speaker = app(SaveLeader::class)->handle(new Leader, ['body' => 'executive_committee', 'translations' => ['en' => ['name' => 'Sample Speaker', 'published' => true]]], staff(SystemRole::Publisher));

    $event = makeEvent([
        'en' => ['title' => 'Sample town hall', 'venue' => 'Sample hall', 'published' => true, 'agenda' => [
            ['time' => '09:00', 'item' => 'Opening'], ['time' => '', 'item' => ' '], ['time' => '10:00', 'item' => 'Questions'],
        ]],
    ], '+2 days 09:00', ['ends_at' => CarbonImmutable::parse('+2 days 12:00')->toDateTimeString(), 'speakers' => [$speaker->id, 'missing'], 'latitude' => '9.31', 'longitude' => '42.12']);

    expect($event->translation('en')?->getAttribute('agenda'))->toEqual([['time' => '09:00', 'item' => 'Opening'], ['time' => '10:00', 'item' => 'Questions']])
        ->and($event->speakers->pluck('id')->all())->toBe([$speaker->id])
        ->and($event->hasMap())->toBeTrue()
        ->and($event->isPast())->toBeFalse();

    expect(fn () => makeEvent(['en' => ['title' => 'Sample']], '+1 day', ['ends_at' => CarbonImmutable::parse('-1 day')->toDateTimeString()]))->toThrow(ValidationException::class)
        ->and(fn () => makeEvent(['en' => ['title' => 'Sample']], '+1 day', ['online_url' => 'http://insecure.example']))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveEvent::class)->handle(new Event, ['translations' => ['en' => ['title' => 'Sample']]], staff(SystemRole::Publisher)))->toThrow(ValidationException::class);
});

it('lists upcoming and past events and shows each event with its details', function () {
    makeEvent(['en' => ['title' => 'Sample future forum', 'summary' => 'Sample summary', 'venue' => 'Sample venue', 'published' => true]], '+5 days 14:00');
    makeEvent(['en' => ['title' => 'Sample earlier forum', 'published' => true]], '-10 days 09:00');
    makeEvent(['en' => ['title' => 'Sample draft forum']], '+6 days');

    $this->get('/en/events')->assertOk()->assertSee('Sample future forum')->assertDontSee('Sample earlier forum')->assertDontSee('Sample draft forum');
    $this->get('/en/events?past=1')->assertOk()->assertSee('Sample earlier forum')->assertDontSee('Sample future forum');

    $html = (string) $this->get('/en/events/sample-future-forum')
        ->assertOk()
        ->assertSee('Sample venue')
        ->assertSee(route('events.ics', ['locale' => 'en', 'slug' => 'sample-future-forum']), escape: false)
        ->assertSee('calendar.google.com', escape: false)
        ->getContent();
    expect($html)->toContain('"@type":"Event"');

    $this->get('/en/events/sample-earlier-forum')->assertOk()->assertSee(__('events.ended', [], 'en'))->assertDontSee('calendar.google.com');
});

it('offers calendar files for one event and for all upcoming events', function () {
    makeEvent(['en' => ['title' => 'Sample calendar meeting, with a long title that needs folding in the calendar file', 'venue' => 'Hall; Room 2', 'published' => true]], '2026-11-02 10:00');

    $single = $this->get('/en/events/sample-calendar-meeting-with-a-long-title-that-needs-folding-in-the-calendar-file.ics')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=UTF-8');
    $body = (string) $single->getContent();
    expect($body)->toContain("BEGIN:VEVENT\r\n")
        ->toContain('DTSTART:20261102T100000Z')
        ->toContain('LOCATION:Hall\; Room 2')
        ->and(collect(explode("\r\n", $body))->every(fn (string $line): bool => strlen($line) <= 75))->toBeTrue();

    $this->get('/en/events/feed.ics')->assertOk()->assertSee('SUMMARY:Sample calendar meeting', escape: false);
});

it('renders the ICS of an all-day event with dates only', function () {
    $event = makeEvent(['en' => ['title' => 'Sample holiday', 'published' => true]], '2026-12-25 00:00', ['all_day' => true]);
    $ics = IcsCalendar::render([['event' => $event, 'translation' => $event->translation('en'), 'url' => 'https://example.org']], 'Sample');

    expect($ics)->toContain('DTSTART;VALUE=DATE:20261225')->toContain('DTEND;VALUE=DATE:20261226');
});

it('shows a month view with events on their day in both calendars', function () {
    makeEvent(['en' => ['title' => 'Sample monthly meeting', 'published' => true]], '2026-11-12 09:00');

    $this->get('/en/events?month=2026-11')
        ->assertOk()
        ->assertSee(__('calendar.gregorian.month.11', [], 'en').' 2026')
        ->assertSee('Sample monthly meeting')
        ->assertSee(__('calendar.weekday.short.1', [], 'en'));
    $this->get('/am/events?month=bad')->assertOk();
});

it('shows upcoming events on the homepage, in search and in the sitemaps, and Amharic events to Harari readers', function () {
    makeEvent(['am' => ['title' => 'የናሙና ጉባኤ', 'published' => true]], '+4 days');
    makeEvent(['en' => ['title' => 'Sample searchable conference', 'published' => true]], '+8 days');

    $this->get('/har/events')->assertOk()->assertSee('የናሙና ጉባኤ')->assertSee('lang="am"', escape: false);
    $this->get('/en')->assertSee('id="home-events"', escape: false)->assertSee('Sample searchable conference');
    $this->get('/en/search?q=conference')->assertSee(__('search.kind.events', [], 'en'))->assertSee('Sample searchable conference');
    $this->get('/sitemaps/en.xml')->assertSee(url('/en/events/sample-searchable-conference'), escape: false);
});

it('lets those who manage events create them in the admin and keeps others out', function () {
    $this->actingAs(staff(SystemRole::Author))->get('/admin/events')->assertForbidden();
    $this->actingAs(staff(SystemRole::OfficeHead))->get('/admin/events')->assertOk();

    $this->actingAs(staff(SystemRole::Editor));
    livewire(CreateEvent::class)
        ->fillForm([
            'starts_at' => '2026-11-20 09:00:00',
            'translations' => ['om' => ['title' => 'Walgahii fakkeenyaa', 'published' => true, 'agenda' => [['time' => '09:00', 'item' => 'Banamuu']]]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $event = Event::query()->firstOrFail();
    // Entered in Addis Ababa time, stored in UTC.
    expect($event->starts_at->utc()->format('H:i'))->toBe('06:00');

    $edit = livewire(EditEvent::class, ['record' => $event->getRouteKey()]);
    // Repeater rows are keyed by random ids, so look at the first row.
    expect(collect($edit->get('data.translations.om.agenda'))->first()['item'] ?? null)->toBe('Banamuu');
    $edit->call('save')->assertHasNoFormErrors();
    expect($event->refresh()->translation('om', fallback: false)?->getAttribute('agenda'))->toEqual([['time' => '09:00', 'item' => 'Banamuu']]);
});
