@props(['video', 'translation', 'headingLevel' => 2])
<article {{ $attributes->class('group relative flex flex-col overflow-hidden rounded-card border border-line bg-surface') }} lang="{{ $translation->locale }}">
    <div class="relative aspect-video bg-ink/80">
        @if ($video->thumbnail)
            <x-picture :media="$video->thumbnail" preset="landscape" sizes="(min-width: 64rem) 25vw, (min-width: 40rem) 50vw, 100vw" class="aspect-video w-full object-cover" />
        @endif
        <span aria-hidden="true" class="absolute inset-0 flex items-center justify-center">
            <span class="flex size-14 items-center justify-center rounded-full bg-black/70 text-2xl text-white group-hover:bg-primary-700">▶</span>
        </span>
        @if ($video->isLiveNow())
            <span class="absolute start-2 top-2 rounded-control bg-danger-600 px-2 py-0.5 text-xs font-semibold uppercase text-white">{{ __('video.live_now') }}</span>
        @endif
    </div>
    <div class="flex flex-1 flex-col gap-2 p-5">
        <h{{ $headingLevel }} class="text-lg font-semibold leading-snug">
            <a href="{{ route('videos.show', ['locale' => $translation->locale, 'slug' => $translation->slug]) }}" class="underline-offset-4 group-hover:underline after:absolute after:inset-0">{{ $translation->getAttribute('title') }}</a>
        </h{{ $headingLevel }}>
        <p class="mt-auto text-xs text-ink-muted">
            {{ __("video.provider.{$video->provider}") }}
            @if ($video->recorded_on)
                · <x-date :date="$video->recorded_on" />
            @endif
        </p>
    </div>
</article>
