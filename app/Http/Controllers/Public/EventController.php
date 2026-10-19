<?php

namespace App\Http\Controllers\Public;

use App\Domain\Content\Support\BodyRenderer;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventTranslation;
use App\Domain\Events\Support\IcsCalendar;
use App\Domain\Organisation\Models\Leader;
use App\Domain\Organisation\Queries\PublicDirectory;
use App\Http\Controllers\Controller;
use App\Services\Calendar\EthiopianCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/**
 * The events calendar (PLAN.md §4 Get involved, ADR-0025): upcoming and
 * past events, a month view, each event's page and calendar files.
 */
final class EventController extends Controller
{
    public function index(Request $request): View
    {
        $locale = app()->getLocale();
        $past = $request->boolean('past');
        $month = $this->month((string) $request->query('month', ''));

        $query = PublicDirectory::visible(Event::query(), $locale)->with('cover');
        if ($month !== null) {
            $events = $query->where('starts_at', '>=', $month->subDays(7)->utc())
                ->where('starts_at', '<', $month->addMonth()->addDays(7)->utc())
                ->orderBy('starts_at')
                ->get();

            return view('pages.events.month', ['events' => $events, 'month' => $month, 'timezone' => EthiopianCalendar::timezone()]);
        }

        $events = ($past ? $query->past()->orderByDesc('starts_at') : $query->upcoming()->orderBy('starts_at'))
            ->paginate(12)
            ->withQueryString();

        return view('pages.events.index', ['events' => $events, 'past' => $past]);
    }

    public function show(string $slug): View
    {
        $locale = app()->getLocale();
        [$event, $translation] = $this->find($slug);

        $url = route('events.show', ['slug' => $translation->slug]);
        $speakers = $event->speakers()->with(['translations', 'portrait'])->get()
            ->map(fn (Leader $leader): ?array => ($shown = PublicDirectory::shown($leader, $locale)) !== null ? ['leader' => $leader, 'translation' => $shown] : null)
            ->filter()
            ->values();

        return view('pages.events.show', [
            'event' => $event,
            'translation' => $translation,
            'body' => BodyRenderer::toHtml($translation->body),
            'speakers' => $speakers,
            'office' => $event->office !== null ? PublicDirectory::shown($event->office, $locale) : null,
            'league' => $event->league !== null ? PublicDirectory::shown($event->league, $locale) : null,
            'alternates' => PublicDirectory::alternates($event, 'events.show', 'events.index'),
            'image' => PublicDirectory::shareImage($event->cover, 'landscape', "event-{$event->id}-{$locale}", $translation->title, __('events.title')),
            'jsonLd' => [array_filter([
                '@type' => 'Event',
                '@id' => $url.'#event',
                'name' => $translation->title,
                'description' => $translation->summary,
                'startDate' => $event->all_day ? $event->startDay()->toDateString() : $event->starts_at->toIso8601String(),
                'endDate' => $event->ends_at?->toIso8601String(),
                'eventAttendanceMode' => $event->online_url !== null && $translation->venue === null
                    ? 'https://schema.org/OnlineEventAttendanceMode'
                    : ($event->online_url !== null ? 'https://schema.org/MixedEventAttendanceMode' : 'https://schema.org/OfflineEventAttendanceMode'),
                'eventStatus' => 'https://schema.org/EventScheduled',
                'location' => $translation->venue !== null
                    ? array_filter(['@type' => 'Place', 'name' => $translation->venue, 'address' => $translation->address])
                    : ($event->online_url !== null ? ['@type' => 'VirtualLocation', 'url' => $event->online_url] : null),
                'organizer' => ['@id' => url('/').'#organization'],
                'url' => $url,
            ], fn (mixed $value): bool => $value !== null)],
        ]);
    }

    public function ics(string $slug): Response
    {
        [$event, $translation] = $this->find($slug);

        return $this->calendar(IcsCalendar::render([[
            'event' => $event,
            'translation' => $translation,
            'url' => route('events.show', ['slug' => $translation->slug]),
        ]], $translation->title), $translation->slug.'.ics');
    }

    public function feed(): Response
    {
        $locale = app()->getLocale();
        $items = [];
        foreach (PublicDirectory::visible(Event::query(), $locale)->upcoming()->orderBy('starts_at')->limit(100)->get() as $event) {
            $shown = PublicDirectory::shown($event, $locale);
            if ($shown instanceof EventTranslation) {
                $items[] = ['event' => $event, 'translation' => $shown, 'url' => route('events.show', ['locale' => $shown->locale, 'slug' => $shown->slug])];
            }
        }

        return $this->calendar(IcsCalendar::render($items, __('site.name').' — '.__('events.title')), 'events.ics');
    }

    /**
     * @return array{0: Event, 1: EventTranslation}
     */
    private function find(string $slug): array
    {
        /** @var EventTranslation|null $translation */
        $translation = EventTranslation::query()->where('locale', app()->getLocale())->where('slug', $slug)->first();
        $event = $translation?->event()->with(['translations', 'cover', 'office.translations', 'league.translations'])->first();
        abort_if($translation === null || $event === null || ! $translation->isLive(), 404);

        return [$event, $translation];
    }

    private function calendar(string $body, string $name): Response
    {
        return response($body, 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="'.$name.'"',
        ]);
    }

    private function month(string $value): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value.'-01', EthiopianCalendar::timezone())?->startOfMonth();
        } catch (Throwable) {
            return null;
        }
    }
}
