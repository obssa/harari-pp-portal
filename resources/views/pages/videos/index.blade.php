@use('App\Domain\Organisation\Queries\PublicDirectory')
@php $locale = app()->getLocale(); @endphp
<x-layouts.public :title="__('video.title')" :description="__('video.description')">
    <x-page-hero :title="__('video.title')" :lead="__('video.description')" tone="ink" :crumbs="[[__('nav.media'), route('news.index')]]">
        <x-slot:actions><x-ui.button :href="route('videos.live')" variant="light" size="sm">{{ $live ? __('video.live_now_link') : __('video.live_page') }}</x-ui.button></x-slot:actions>
    </x-page-hero>
    <div class="mx-auto max-w-page px-4 pt-2 pb-12 sm:px-6">

        @if ($playlists->isNotEmpty())
            <nav aria-label="{{ __('video.playlists') }}" class="mt-6">
                <ul class="flex flex-wrap gap-2 text-sm">
                    <li>
                        <a href="{{ route('videos.index') }}" @if (! $playlist) aria-current="page" @endif
                           @class(['inline-block rounded-full border px-3 py-1', 'border-primary-600 bg-primary-50 text-primary-800 dark:bg-primary-950 dark:text-primary-200' => ! $playlist, 'border-line hover:bg-surface-muted' => (bool) $playlist])>{{ __('news.all') }}</a>
                    </li>
                    @foreach ($playlists as $option)
                        @continue($option->translation() === null)
                        <li>
                            <a href="{{ route('videos.index', ['playlist' => $option->translation()?->slug]) }}" @if ($playlist?->is($option)) aria-current="page" @endif
                               @class(['inline-block rounded-full border px-3 py-1', 'border-primary-600 bg-primary-50 text-primary-800 dark:bg-primary-950 dark:text-primary-200' => (bool) $playlist?->is($option), 'border-line hover:bg-surface-muted' => ! $playlist?->is($option)])>{{ $option->name() }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif

        @if ($videos->isEmpty())
            <p class="mt-10 rounded-card border border-line bg-surface px-5 py-8 text-center text-ink-muted">{{ __('directory.empty') }}</p>
        @else
            <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($videos as $video)
                    @php $shown = PublicDirectory::shown($video, $locale); @endphp
                    @continue($shown === null)
                    <li><x-video-card :video="$video" :translation="$shown" /></li>
                @endforeach
            </ul>

            <div class="mt-10">{{ $videos->links() }}</div>
        @endif
    </div>
</x-layouts.public>
