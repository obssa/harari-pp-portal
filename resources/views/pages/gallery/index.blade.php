@use('App\Domain\Organisation\Queries\PublicDirectory')
@php $locale = app()->getLocale(); @endphp
<x-layouts.public :title="__('gallery.title')" :description="__('gallery.description')">
    <x-page-hero :title="__('gallery.title')" :lead="__('gallery.description')" tone="ink" :crumbs="[[__('nav.media'), route('news.index')]]" />
    <div class="mx-auto max-w-page px-4 pt-2 pb-12 sm:px-6">

        @if ($galleries->isEmpty())
            <p class="mt-10 rounded-card border border-line bg-surface px-5 py-8 text-center text-ink-muted">{{ __('directory.empty') }}</p>
        @else
            <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($galleries as $gallery)
                    @php $shown = PublicDirectory::shown($gallery, $locale); @endphp
                    @continue($shown === null)
                    <li lang="{{ $shown->locale }}">
                        <x-ui.card :title="$shown->getAttribute('title')" :href="route('gallery.show', ['locale' => $shown->locale, 'slug' => $shown->slug])" :media="$gallery->cover" preset="photo" :heading-level="2">
                            {{ $shown->getAttribute('summary') }}
                            <x-slot:meta>
                                {{ trans_choice('gallery.photos', $gallery->items_count, ['count' => $gallery->items_count]) }}
                                @if ($gallery->taken_on)
                                    · <x-date :date="$gallery->taken_on" />
                                @endif
                            </x-slot:meta>
                        </x-ui.card>
                    </li>
                @endforeach
            </ul>

            <div class="mt-10">{{ $galleries->links() }}</div>
        @endif

        <p class="mt-12 text-sm">
            <a href="{{ route('news.index') }}" class="text-link underline-offset-4 hover:underline">{{ __('nav.news') }}</a>
        </p>
    </div>
</x-layouts.public>
