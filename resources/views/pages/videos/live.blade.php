@use('App\Domain\Organisation\Queries\PublicDirectory')
@php $locale = app()->getLocale(); @endphp
<x-layouts.public :title="__('video.live_page')" :description="__('video.live_description')">
    <div class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <h1 class="heading-1">{{ __('video.live_page') }}</h1>

        @php $current = $now !== null ? PublicDirectory::shown($now, $locale) : null; @endphp
        @if ($current)
            @php $link = $now->link(); @endphp
            <section aria-labelledby="live-now" class="mt-8 max-w-4xl" lang="{{ $current->locale }}">
                <p class="text-sm font-semibold uppercase tracking-wide text-danger-600">{{ __('video.live_now') }}</p>
                <h2 id="live-now" class="heading-2 mt-1">{{ $current->getAttribute('title') }}</h2>
                @if ($link?->embedUrl())
                    <iframe src="{{ $link->embedUrl() }}" title="{{ $current->getAttribute('title') }}" allowfullscreen
                            allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen"
                            referrerpolicy="strict-origin-when-cross-origin"
                            class="mt-4 aspect-video w-full rounded-card border border-line"></iframe>
                @else
                    <p class="mt-4"><x-ui.button :href="$now->url">{{ __('video.watch_on', ['provider' => __("video.provider.{$now->provider}")]) }}</x-ui.button></p>
                @endif
            </section>
        @else
            <p class="mt-6 max-w-prose rounded-card border border-line bg-surface px-5 py-6 text-ink-muted">{{ __('video.no_live') }}</p>
        @endif

        @php $upcoming = $next !== null ? PublicDirectory::shown($next, $locale) : null; @endphp
        @if ($upcoming)
            <section aria-labelledby="live-next" class="mt-10 max-w-3xl rounded-card border border-line bg-surface p-5" lang="{{ $upcoming->locale }}">
                <h2 id="live-next" class="text-sm font-semibold uppercase tracking-wide text-ink-muted">{{ __('video.next_live') }}</h2>
                <p class="mt-2 text-lg font-semibold">
                    <a href="{{ route('videos.show', ['locale' => $upcoming->locale, 'slug' => $upcoming->slug]) }}" class="underline-offset-4 hover:underline">{{ $upcoming->getAttribute('title') }}</a>
                </p>
                <p class="mt-1 text-sm text-ink-muted"><x-date :date="$next->live_starts_at" :time="true" /></p>
            </section>
        @endif

        @if ($recent->isNotEmpty())
            <section aria-labelledby="live-recent" class="mt-12">
                <h2 id="live-recent" class="heading-2">{{ __('video.recent') }}</h2>
                <ul class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($recent as $video)
                        @php $shown = PublicDirectory::shown($video, $locale); @endphp
                        @continue($shown === null)
                        <li><x-video-card :video="$video" :translation="$shown" :heading-level="3" /></li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-layouts.public>
