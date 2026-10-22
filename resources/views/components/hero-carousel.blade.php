@props(['slides', 'settings', 'label'])
@php
    $locale = app()->getLocale();
    $count = count($slides);
    $height = match ($settings['height'] ?? 'standard') {
        'compact' => 'min-h-[26rem] lg:min-h-[30rem]',
        'tall' => 'min-h-[38rem] lg:min-h-[46rem]',
        default => 'min-h-[32rem] lg:min-h-[38rem]',
    };
    $focus = ($settings['transition'] ?? 'slide') === 'focus';
    $titles = [];
    foreach ($slides as $item) {
        $titles[] = $item['kind'] === 'slide' ? $item['slide']->text('title', $locale)[0] : $item['translation']->title;
    }
@endphp
{{-- ADR-0070: the homepage carousel, after the WAI carousel pattern. Slides sit side by side in a
     scroll-snapping track, so they can be swiped without script; the script turns them, pauses on
     hover and focus, and stops for visitors who ask for less motion. --}}
<div data-carousel role="region" aria-roledescription="{{ __('homepage.carousel.roledescription') }}" aria-label="{{ $label }}"
     class="relative isolate bg-primary-950"
     data-interval="{{ (int) ($settings['interval'] ?? 7) * 1000 }}"
     data-autoplay="{{ ($settings['autoplay'] ?? true) && $count > 1 ? 'true' : 'false' }}"
     data-hover="{{ ($settings['pause_on_hover'] ?? true) ? 'pause' : 'keep' }}"
     data-label-pause="{{ __('homepage.carousel.pause') }}" data-label-play="{{ __('homepage.carousel.play') }}"
     @if ($focus) data-mode="focus" @endif>
    <div data-track id="hero-track" aria-live="off" class="flex snap-x snap-mandatory overflow-x-auto">
        @foreach ($slides as $index => $item)
            <div id="hero-slide-{{ $index + 1 }}" role="group" aria-roledescription="{{ __('homepage.carousel.slide_role') }}"
                 aria-label="{{ __('homepage.carousel.position', ['number' => $index + 1, 'count' => $count]) }}"
                 @if ($item['kind'] === 'slide' && $item['slide']->duration) data-duration="{{ $item['slide']->duration * 1000 }}" @endif
                 @class(['flex w-full shrink-0 snap-start snap-always', 'is-current' => $index === 0])>
                @if ($item['kind'] === 'slide')
                    @include('pages.home.slides.'.$item['slide']->layout->value, ['slide' => $item['slide'], 'index' => $index, 'height' => $height])
                @else
                    @include('pages.home.slides.story', ['post' => $item['post'], 'translation' => $item['translation'], 'index' => $index, 'height' => $height])
                @endif
            </div>
        @endforeach
    </div>

    @if ($count > 1)
        <div data-controls class="pointer-events-none absolute inset-x-0 bottom-0 z-10">
            <div class="mx-auto flex max-w-page items-end px-4 pb-4 sm:px-6 sm:pb-6">
                <div class="pointer-events-auto inline-flex items-center gap-1 rounded-full bg-primary-950/80 p-1 text-white shadow-lg ring-1 ring-white/15 backdrop-blur">
                    <button type="button" data-toggle aria-controls="hero-track" aria-label="{{ __('homepage.carousel.pause') }}"
                            class="inline-flex size-9 items-center justify-center rounded-full hover:bg-white/15 focus-visible:outline-2 focus-visible:outline-gold-400">
                        <svg data-icon="pause" aria-hidden="true" class="size-4" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>
                        <svg data-icon="play" aria-hidden="true" class="hidden size-4" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.5-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5z"/></svg>
                    </button>
                    @if ($settings['show_arrows'] ?? true)
                        <button type="button" data-prev aria-controls="hero-track" aria-label="{{ __('homepage.carousel.previous') }}"
                                class="inline-flex size-9 items-center justify-center rounded-full hover:bg-white/15 focus-visible:outline-2 focus-visible:outline-gold-400">
                            <svg aria-hidden="true" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                        </button>
                    @endif
                    @if ($settings['show_dots'] ?? true)
                        <ul class="flex items-center gap-1 px-1">
                            @foreach ($titles as $index => $title)
                                <li>
                                    <button type="button" data-dot="{{ $index }}" aria-controls="hero-track" aria-current="{{ $index === 0 ? 'true' : 'false' }}"
                                            aria-label="{{ __('homepage.carousel.go_to', ['number' => $index + 1, 'title' => $title]) }}"
                                            class="group flex h-9 items-center px-1 focus-visible:outline-2 focus-visible:outline-gold-400">
                                        <span aria-hidden="true" class="block h-2 w-2 rounded-full bg-white/50 transition-[width,background-color] duration-300 group-hover:bg-white group-aria-[current=true]:w-6 group-aria-[current=true]:bg-gold-400"></span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($settings['show_arrows'] ?? true)
                        <button type="button" data-next aria-controls="hero-track" aria-label="{{ __('homepage.carousel.next') }}"
                                class="inline-flex size-9 items-center justify-center rounded-full hover:bg-white/15 focus-visible:outline-2 focus-visible:outline-gold-400">
                            <svg aria-hidden="true" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                        </button>
                    @endif
                </div>
            </div>
            @if ($settings['show_progress'] ?? true)
                <span aria-hidden="true" class="absolute inset-x-0 bottom-0 block h-1 bg-white/10"><span data-progress class="block h-full bg-gold-500"></span></span>
            @endif
        </div>
        @vite('resources/js/carousel.js')
    @endif
</div>
