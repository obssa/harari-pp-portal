@props(['statistic'])
@php
    $translation = $statistic->translation();
    $unit = $translation?->getAttribute('unit');
    $source = $translation?->getAttribute('source');
@endphp
<figure {{ $attributes->class('rounded-card border border-line bg-surface p-4') }} @if ($translation) lang="{{ $translation->locale }}" @endif>
    <p class="text-3xl font-bold tabular-nums text-primary-700 dark:text-primary-300" dir="ltr">{{ $statistic->formatted() }}@if (filled($unit))<span class="ms-1 text-base font-semibold text-ink-muted">{{ $unit }}</span>@endif</p>
    <figcaption class="mt-1">
        <span class="block font-medium">{{ $translation?->getAttribute('label') }}</span>
        @if (filled($source) || $statistic->as_of)
            <span class="mt-1 block text-xs text-ink-muted">
                @if (filled($source))
                    @if ($statistic->source_url)
                        <a href="{{ $statistic->source_url }}" rel="noopener" class="underline-offset-2 hover:underline">{{ $source }}</a>
                    @else
                        {{ $source }}
                    @endif
                @endif
                @if ($statistic->as_of)
                    · {{ __('projects.as_of') }} <x-date :date="$statistic->as_of" />
                @endif
            </span>
        @endif
    </figcaption>
</figure>
