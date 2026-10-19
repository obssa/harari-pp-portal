@props(['date'])
@php
    $calendar = app(\App\Services\Calendar\EthiopianCalendar::class);
    $value = \Carbon\CarbonImmutable::instance($date)->setTimezone(\App\Services\Calendar\EthiopianCalendar::timezone());
    $ethiopian = $calendar->toEthiopian($value);
@endphp
<time datetime="{{ $value->toDateString() }}" {{ $attributes->class('inline-flex w-20 flex-col items-center rounded-card border border-line bg-surface px-2 py-2 text-center') }}>
    <span class="text-2xl font-bold leading-none text-primary-700 dark:text-primary-300">{{ $ethiopian->day }}</span>
    <span class="mt-1 text-xs font-medium">{{ $ethiopian->monthName() }}</span>
    <span class="mt-1 border-t border-line pt-1 text-[0.7rem] text-ink-muted">{{ $calendar->formatGregorian($value) }}</span>
</time>
