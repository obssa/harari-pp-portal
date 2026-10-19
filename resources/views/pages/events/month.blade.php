@use('App\Domain\Organisation\Queries\PublicDirectory')
@php
    $locale = app()->getLocale();
    $calendar = app(\App\Services\Calendar\EthiopianCalendar::class);
    $first = $month->startOfMonth();
    // Weeks start on Monday, as on Ethiopian wall calendars.
    $start = $first->subDays(($first->dayOfWeekIso + 6) % 7);
    $byDay = $events->groupBy(fn ($event) => $event->starts_at->setTimezone($timezone)->toDateString());
    $weekdays = array_map(fn (int $day): string => __("calendar.weekday.short.{$day}"), range(1, 7));
    $monthLabel = __("calendar.gregorian.month.{$first->month}").' '.$first->year;
@endphp
<x-layouts.public :title="__('events.month_title', ['month' => $monthLabel])" :description="__('events.description')">
    <div class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <h1 class="heading-1">{{ __('events.title') }}</h1>

        <nav aria-label="{{ __('events.month_navigation') }}" class="mt-6 flex flex-wrap items-center gap-3 text-sm">
            <a href="{{ route('events.index', ['month' => $first->subMonth()->format('Y-m')]) }}" class="rounded-control border border-line px-3 py-1.5 hover:bg-surface-muted">{{ __('events.previous_month') }}</a>
            <p class="font-semibold">{{ $monthLabel }}</p>
            <a href="{{ route('events.index', ['month' => $first->addMonth()->format('Y-m')]) }}" class="rounded-control border border-line px-3 py-1.5 hover:bg-surface-muted">{{ __('events.next_month') }}</a>
            <a href="{{ route('events.index') }}" class="ms-auto text-link underline-offset-4 hover:underline">{{ __('events.list_view') }}</a>
        </nav>

        <div class="mt-6 overflow-x-auto">
            <table class="w-full min-w-[42rem] table-fixed border-collapse text-sm">
                <thead>
                    <tr>
                        @foreach ($weekdays as $weekday)
                            <th scope="col" class="border border-line bg-surface-muted px-2 py-1.5 text-start font-medium">{{ $weekday }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @for ($week = 0; $week < 6; $week++)
                        @php $weekStart = $start->addWeeks($week); @endphp
                        @break($week > 3 && $weekStart->month !== $first->month)
                        <tr>
                            @foreach (range(0, 6) as $offset)
                                @php
                                    $day = $weekStart->addDays($offset);
                                    $ethiopian = $calendar->toEthiopian($day);
                                @endphp
                                <td @class(['h-24 border border-line p-1.5 align-top', 'bg-surface' => $day->month === $first->month, 'bg-surface-muted text-ink-muted' => $day->month !== $first->month])>
                                    <span class="flex justify-between text-xs">
                                        <span class="font-semibold">{{ $day->day }}</span>
                                        <span class="text-ink-muted" title="{{ $ethiopian->monthName() }}">{{ $ethiopian->day }}</span>
                                    </span>
                                    @foreach ($byDay->get($day->toDateString(), collect()) as $event)
                                        @php $shown = PublicDirectory::shown($event, $locale); @endphp
                                        @continue($shown === null)
                                        <a href="{{ route('events.show', ['locale' => $shown->locale, 'slug' => $shown->slug]) }}" lang="{{ $shown->locale }}"
                                           class="mt-1 block truncate rounded bg-primary-50 px-1.5 py-0.5 text-xs text-primary-800 hover:underline dark:bg-primary-950 dark:text-primary-200">{{ $shown->getAttribute('title') }}</a>
                                    @endforeach
                                </td>
                            @endforeach
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.public>
