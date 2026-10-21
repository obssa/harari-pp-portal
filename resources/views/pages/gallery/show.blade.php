@php
    $locale = app()->getLocale();
    $total = $items->count();
@endphp
<x-layouts.public
    :title="$translation->title"
    :description="$translation->summary"
    :alternates="$alternates"
    :image="$image"
    :json-ld="$jsonLd"
>
    <article id="gallery">
        {{-- ADR-0062: the title of a page sits on the plaster band. --}}
        <div class="bg-surface-muted">
            <div class="mx-auto max-w-page px-4 pt-8 pb-10 sm:px-6">
                <nav aria-label="{{ __('news.breadcrumb') }}" class="text-sm text-ink-muted">
                    <ol class="flex flex-wrap gap-1">
                        <li><a href="{{ route('home') }}" class="hover:underline">{{ __('nav.home') }}</a> /</li>
                        <li><a href="{{ route('gallery.index') }}" class="hover:underline">{{ __('gallery.title') }}</a></li>
                    </ol>
                </nav>

                <header class="mt-6 max-w-3xl">
                    <h1 class="heading-1">{{ $translation->title }}</h1>
                    @if ($translation->summary)
                        <p class="mt-4 text-lg text-ink-muted">{{ $translation->summary }}</p>
                    @endif
                    <p class="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-sm text-ink-muted">
                        <span>{{ trans_choice('gallery.photos', $total, ['count' => $total]) }}</span>
                        @if ($gallery->taken_on)
                            <span>{{ __('gallery.taken_on') }}: <x-date :date="$gallery->taken_on" /></span>
                        @endif
                        @if ($office)
                            <a href="{{ route('offices.show', ['locale' => $office->locale, 'slug' => $office->slug]) }}" class="text-link hover:underline">{{ $office->getAttribute('name') }}</a>
                        @endif
                        @if ($league)
                            <a href="{{ route('leagues.show', ['locale' => $league->locale, 'slug' => $league->slug]) }}" class="text-link hover:underline">{{ $league->getAttribute('name') }}</a>
                        @endif
                    </p>
                </header>
            </div>
        </div>
        <div class="mx-auto max-w-page px-4 pt-2 pb-12 sm:px-6">

            @if ($total === 0)
                <p class="mt-10 rounded-card border border-line bg-surface px-5 py-8 text-center text-ink-muted">{{ __('directory.empty') }}</p>
            @else
                <ul class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($items as $index => $item)
                        @php $caption = $item->captionFor($locale); @endphp
                        <li>
                            <a href="#photo-{{ $index + 1 }}" class="block overflow-hidden rounded-card focus-visible:outline-2"
                               aria-label="{{ __('gallery.open_photo', ['number' => $index + 1, 'total' => $total]) }}">
                                <x-picture :media="$item->media" preset="square" sizes="(min-width: 64rem) 16rem, 50vw" :loading="$index < 4 ? 'eager' : 'lazy'"
                                           class="aspect-square w-full object-cover transition-transform duration-150 hover:scale-105" />
                            </a>
                            @if ($caption !== '')
                                <p class="mt-1 text-xs text-ink-muted">{{ $caption }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>

                {{-- Lightbox: each photo opens on its own anchor; works without JavaScript. --}}
                @foreach ($items as $index => $item)
                    @php
                        $number = $index + 1;
                        $caption = $item->captionFor($locale);
                    @endphp
                    <section id="photo-{{ $number }}" aria-label="{{ __('gallery.photo_of', ['number' => $number, 'total' => $total]) }}"
                             class="fixed inset-0 z-50 hidden flex-col items-center justify-center gap-3 bg-black/90 p-4 text-white target:flex">
                        <x-picture :media="$item->media" preset="landscape" sizes="100vw" loading="lazy" class="max-h-[78vh] w-auto max-w-full object-contain" />
                        <p class="text-center text-sm">
                            <span class="opacity-75">{{ __('gallery.photo_of', ['number' => $number, 'total' => $total]) }}</span>
                            @if ($caption !== '')
                                · {{ $caption }}
                            @endif
                            @if (filled($item->media->credit))
                                · {{ __('content.photo_credit', ['credit' => $item->media->credit]) }}
                            @endif
                        </p>
                        <nav class="flex flex-wrap justify-center gap-2 text-sm" aria-label="{{ __('gallery.navigation') }}">
                            @if ($number > 1)
                                <a href="#photo-{{ $number - 1 }}" class="rounded-control border border-white/40 px-3 py-1.5 hover:bg-white/10">{{ __('gallery.previous') }}</a>
                            @endif
                            @if ($number < $total)
                                <a href="#photo-{{ $number + 1 }}" class="rounded-control border border-white/40 px-3 py-1.5 hover:bg-white/10">{{ __('gallery.next') }}</a>
                            @endif
                            @if ($gallery->allow_download)
                                <a href="{{ route('media.file', ['uuid' => $item->media->uuid, 'name' => $item->media->file_name]) }}" download class="rounded-control border border-white/40 px-3 py-1.5 hover:bg-white/10">{{ __('gallery.download') }}</a>
                            @endif
                            <a href="#gallery" class="rounded-control bg-white px-3 py-1.5 font-medium text-black hover:bg-white/90">{{ __('gallery.close') }}</a>
                        </nav>
                    </section>
                @endforeach
            @endif
        </div>
    </article>
</x-layouts.public>
