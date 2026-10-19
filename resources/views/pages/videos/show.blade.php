<x-layouts.public
    :title="$translation->title"
    :description="$translation->summary"
    :alternates="$alternates"
    :image="$image"
    type="video.other"
    :json-ld="$jsonLd"
>
    <article class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <nav aria-label="{{ __('news.breadcrumb') }}" class="text-sm text-ink-muted">
            <ol class="flex flex-wrap gap-1">
                <li><a href="{{ route('home') }}" class="hover:underline">{{ __('nav.home') }}</a> /</li>
                <li><a href="{{ route('videos.index') }}" class="hover:underline">{{ __('video.title') }}</a></li>
            </ol>
        </nav>

        <header class="mt-6 max-w-3xl">
            @if ($video->isLiveNow())
                <p class="text-sm font-semibold uppercase tracking-wide text-danger-600">{{ __('video.live_now') }}</p>
            @endif
            <h1 class="heading-1 mt-2">{{ $translation->title }}</h1>
            <p class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm text-ink-muted">
                <span>{{ __("video.provider.{$video->provider}") }}</span>
                @if ($video->recorded_on)
                    <span><x-date :date="$video->recorded_on" /></span>
                @endif
                @if ($office)
                    <a href="{{ route('offices.show', ['locale' => $office->locale, 'slug' => $office->slug]) }}" class="text-link hover:underline">{{ $office->getAttribute('name') }}</a>
                @endif
                @if ($league)
                    <a href="{{ route('leagues.show', ['locale' => $league->locale, 'slug' => $league->slug]) }}" class="text-link hover:underline">{{ $league->getAttribute('name') }}</a>
                @endif
            </p>
        </header>

        <div class="mt-8 max-w-4xl">
            @if ($link?->embedUrl())
                <iframe src="{{ $link->embedUrl() }}" title="{{ $translation->title }}" loading="lazy" allowfullscreen
                        allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen"
                        referrerpolicy="strict-origin-when-cross-origin"
                        class="aspect-video w-full rounded-card border border-line"></iframe>
            @else
                <a href="{{ $video->url }}" rel="noopener" class="group relative block overflow-hidden rounded-card border border-line bg-ink/80">
                    @if ($video->thumbnail)
                        <x-picture :media="$video->thumbnail" preset="landscape" sizes="(min-width: 64rem) 56rem, 100vw" loading="eager" fetchpriority="high" class="aspect-video w-full object-cover" />
                    @else
                        <span class="block aspect-video"></span>
                    @endif
                    <span class="absolute inset-0 flex items-center justify-center">
                        <span class="rounded-control bg-black/75 px-4 py-2 font-medium text-white group-hover:bg-primary-700">{{ __('video.watch_on', ['provider' => __("video.provider.{$video->provider}")]) }}</span>
                    </span>
                </a>
            @endif
            <p class="mt-2 text-sm"><a href="{{ $video->url }}" rel="noopener" class="text-link underline-offset-4 hover:underline">{{ __('video.watch_on', ['provider' => __("video.provider.{$video->provider}")]) }}</a></p>
        </div>

        @if ($translation->summary)
            <p class="mt-6 max-w-prose whitespace-pre-line text-lg">{{ $translation->summary }}</p>
        @endif
    </article>
</x-layouts.public>
