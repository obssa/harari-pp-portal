@php
    $map = null;
    if ($event->hasMap()) {
        $lat = (float) $event->latitude;
        $lng = (float) $event->longitude;
        $map = [
            'embed' => 'https://www.openstreetmap.org/export/embed.html?bbox='.urlencode(implode(',', [$lng - 0.006, $lat - 0.004, $lng + 0.006, $lat + 0.004])).'&layer=mapnik&marker='.urlencode($lat.','.$lng),
            'link' => "https://www.openstreetmap.org/?mlat={$lat}&mlon={$lng}#map=17/{$lat}/{$lng}",
        ];
    }
    $google = 'https://calendar.google.com/calendar/render?'.http_build_query([
        'action' => 'TEMPLATE',
        'text' => $translation->title,
        'dates' => $event->all_day
            ? $event->startDay()->format('Ymd').'/'.$event->dayAfter()->format('Ymd')
            : $event->starts_at->utc()->format('Ymd\THis\Z').'/'.($event->ends_at ?? $event->starts_at->addHours(2))->utc()->format('Ymd\THis\Z'),
        'details' => route('events.show', ['slug' => $translation->slug]),
        'location' => (string) $translation->venue,
    ]);
@endphp
<x-layouts.public
    :title="$translation->title"
    :description="$translation->summary"
    :alternates="$alternates"
    :image="$image"
    :json-ld="$jsonLd"
>
    <article class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <nav aria-label="{{ __('news.breadcrumb') }}" class="text-sm text-ink-muted">
            <ol class="flex flex-wrap gap-1">
                <li><a href="{{ route('home') }}" class="hover:underline">{{ __('nav.home') }}</a> /</li>
                <li><a href="{{ route('events.index') }}" class="hover:underline">{{ __('events.title') }}</a></li>
            </ol>
        </nav>

        <header class="mt-6 flex max-w-3xl gap-5">
            <x-ui.date-tile :date="$event->starts_at" class="shrink-0" />
            <div>
                @if ($event->isPast())
                    <p class="text-sm font-semibold uppercase tracking-wide text-ink-muted">{{ __('events.ended') }}</p>
                @endif
                <h1 class="heading-1">{{ $translation->title }}</h1>
                @if ($translation->summary)
                    <p class="mt-3 text-lg text-ink-muted">{{ $translation->summary }}</p>
                @endif
            </div>
        </header>

        <div class="mt-10 grid gap-10 lg:grid-cols-3">
            <div class="lg:col-span-2">
                @if ($event->cover)
                    <x-picture :media="$event->cover" preset="landscape" sizes="(min-width: 64rem) 42rem, 100vw" loading="eager" fetchpriority="high" class="mb-8 w-full rounded-card" />
                @endif

                @if (trim($body) !== '')
                    <div class="prose-content max-w-prose">{!! $body !!}</div>
                @endif

                @if (! empty($translation->agenda))
                    <section aria-labelledby="event-agenda" class="mt-10">
                        <h2 id="event-agenda" class="heading-2">{{ __('events.agenda') }}</h2>
                        <ol class="mt-4 divide-y divide-line rounded-card border border-line bg-surface">
                            @foreach ($translation->agenda as $line)
                                <li class="flex gap-4 p-3">
                                    <span class="w-16 shrink-0 font-medium tabular-nums" dir="ltr">{{ $line['time'] }}</span>
                                    <span>{{ $line['item'] }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </section>
                @endif

                @if ($speakers->isNotEmpty())
                    <section aria-labelledby="event-speakers" class="mt-10">
                        <h2 id="event-speakers" class="heading-2">{{ __('events.speakers') }}</h2>
                        <ul class="mt-4 grid gap-4 sm:grid-cols-2">
                            @foreach ($speakers as $speaker)
                                <li><x-directory.person :leader="$speaker['leader']" :translation="$speaker['translation']" :heading-level="3" /></li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($map)
                    <section aria-labelledby="event-map" class="mt-10">
                        <h2 id="event-map" class="heading-3">{{ __('offices.map') }}</h2>
                        <iframe src="{{ $map['embed'] }}" title="{{ __('offices.map_of', ['office' => $translation->venue ?: $translation->title]) }}" loading="lazy" referrerpolicy="no-referrer"
                                class="mt-4 aspect-video w-full rounded-card border border-line"></iframe>
                        <p class="mt-2 text-sm"><a href="{{ $map['link'] }}" rel="noopener" class="text-link underline-offset-4 hover:underline">{{ __('offices.larger_map') }}</a></p>
                    </section>
                @endif
            </div>

            <aside class="space-y-6" aria-label="{{ __('events.details') }}">
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="font-semibold">{{ __('events.when') }}</dt>
                        <dd class="mt-0.5 text-ink-muted">
                            <x-date :date="$event->starts_at" :time="! $event->all_day" />
                            @if ($event->ends_at)
                                – <x-date :date="$event->ends_at" :time="! $event->all_day" />
                            @endif
                        </dd>
                    </div>
                    @if (filled($translation->venue) || filled($translation->address))
                        <div>
                            <dt class="font-semibold">{{ __('events.where') }}</dt>
                            <dd class="mt-0.5 whitespace-pre-line text-ink-muted">{{ trim($translation->venue."\n".$translation->address) }}</dd>
                        </div>
                    @endif
                    @if ($event->online_url)
                        <div>
                            <dt class="font-semibold">{{ __('events.online') }}</dt>
                            <dd class="mt-0.5"><a href="{{ $event->online_url }}" rel="noopener" class="text-link underline-offset-4 hover:underline">{{ __('events.join_online') }}</a></dd>
                        </div>
                    @endif
                    @if ($office)
                        <div>
                            <dt class="font-semibold">{{ __('admin.leaders.field.office') }}</dt>
                            <dd class="mt-0.5"><a href="{{ route('offices.show', ['locale' => $office->locale, 'slug' => $office->slug]) }}" class="text-link hover:underline">{{ $office->getAttribute('name') }}</a></dd>
                        </div>
                    @endif
                    @if ($league)
                        <div>
                            <dt class="font-semibold">{{ __('admin.leaders.field.league') }}</dt>
                            <dd class="mt-0.5"><a href="{{ route('leagues.show', ['locale' => $league->locale, 'slug' => $league->slug]) }}" class="text-link hover:underline">{{ $league->getAttribute('name') }}</a></dd>
                        </div>
                    @endif
                </dl>

                @if (! $event->isPast())
                    <div class="flex flex-wrap gap-2 text-sm">
                        <a href="{{ route('events.ics', ['slug' => $translation->slug]) }}" class="rounded-control border border-line px-3 py-1.5 hover:bg-surface-muted">{{ __('events.add_to_calendar') }}</a>
                        <a href="{{ $google }}" rel="noopener" class="rounded-control border border-line px-3 py-1.5 hover:bg-surface-muted">{{ __('events.google_calendar') }}</a>
                    </div>
                @endif

            </aside>
        </div>
    </article>
</x-layouts.public>
