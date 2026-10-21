@props(['date'])
@php
    $calendar = app(\App\Services\Calendar\EthiopianCalendar::class);
    $value = \Carbon\CarbonImmutable::instance($date)->setTimezone(\App\Services\Calendar\EthiopianCalendar::timezone());
    $ethiopian = $calendar->toEthiopian($value);
@endphp
{{-- ADR-0062: Ethiopian month on a blue band, the day large, the Gregorian date below. --}}
<time datetime="{{ $value->toDateString() }}" {{ $attributes->class('inline-flex w-21 flex-col overflow-hidden rounded-card border border-line bg-surface text-center') }}>
    <span class="bg-primary-600 px-1 py-1 text-xs font-bold text-white">{{ $ethiopian->monthName() }}</span>
    <span class="pt-1.5 text-3xl font-extrabold leading-none">{{ $ethiopian->day }}</span>
    <span class="px-1 pt-1 pb-2 text-[0.7rem] text-ink-muted">{{ $calendar->formatGregorian($value) }}</span>
</time>
