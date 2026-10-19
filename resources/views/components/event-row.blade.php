@props(['event', 'translation', 'headingLevel' => 2])
@php $timezone = \App\Services\Calendar\EthiopianCalendar::timezone(); @endphp
<article {{ $attributes->class('group relative flex gap-4 rounded-card border border-line bg-surface p-4') }} lang="{{ $translation->locale }}">
    <x-ui.date-tile :date="$event->starts_at" class="shrink-0" />
    <div class="min-w-0">
        <h{{ $headingLevel }} class="font-semibold leading-snug">
            <a href="{{ route('events.show', ['locale' => $translation->locale, 'slug' => $translation->slug]) }}" class="underline-offset-4 group-hover:underline after:absolute after:inset-0">{{ $translation->getAttribute('title') }}</a>
        </h{{ $headingLevel }}>
        <p class="mt-1 text-sm text-ink-muted">
            @if ($event->all_day)
                {{ __('events.all_day') }}
            @else
                <time datetime="{{ $event->starts_at->toIso8601String() }}">{{ $event->starts_at->setTimezone($timezone)->format('H:i') }}</time>
                @if ($event->ends_at)
                    – {{ $event->ends_at->setTimezone($timezone)->format('H:i') }}
                @endif
            @endif
            @if (filled($translation->getAttribute('venue')))
                · {{ $translation->getAttribute('venue') }}
            @elseif ($event->online_url)
                · {{ __('events.online') }}
            @endif
        </p>
        @if (filled($translation->getAttribute('summary')))
            <p class="mt-2 text-sm text-ink-muted">{{ \Illuminate\Support\Str::limit((string) $translation->getAttribute('summary'), 180) }}</p>
        @endif
    </div>
</article>
