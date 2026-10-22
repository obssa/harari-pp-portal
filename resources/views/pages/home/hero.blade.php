@php
    $slides = $data['slides'] ?? [];
    $custom = trim((string) $section->setting('titles.'.app()->getLocale(), ''));
@endphp
@if ($slides !== [])
    {{-- ADR-0070: the carousel an editor arranges under Content → Homepage carousel, then featured stories. --}}
    <section>
        <x-hero-carousel :slides="$slides" :settings="$data['settings']" :label="$custom !== '' ? $custom : __('homepage.carousel.label')" />
    </section>
    <span id="home-after-hero"></span>
@else
    {{-- ADR-0065/0066: with no slides, Harar Gate behind the welcome, night blue over it, basket plates turning slowly. --}}
    <section aria-label="{{ $section->heading() }}" class="bg-surface-muted">
        <div class="relative isolate overflow-hidden bg-primary-950 text-white">
            <x-harar-photo name="gate" sizes="100vw" loading="eager" fetchpriority="high" class="animate-zoom absolute inset-0 -z-20 size-full object-cover object-[70%_50%]" />
            <div aria-hidden="true" class="absolute inset-0 -z-10 bg-linear-to-r from-primary-950 via-primary-950/85 to-primary-900/25"></div>
            <div aria-hidden="true" class="absolute inset-x-0 bottom-0 -z-10 h-1/3 bg-linear-to-t from-primary-950/90 to-transparent"></div>
            <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-[5] overflow-hidden">
                <div class="absolute -end-56 top-1/2 size-[40rem] -translate-y-1/2 opacity-[0.18] lg:-end-48">
                    @include('pages.home._plate', ['class' => 'animate-spin-slow size-full'])
                </div>
                <div class="absolute -start-24 -bottom-28 size-72 opacity-20">
                    @include('pages.home._plate', ['class' => 'animate-spin-slower size-full'])
                </div>
                <div class="absolute end-[6%] top-10 hidden size-20 opacity-40 lg:block">
                    @include('pages.home._plate', ['class' => 'animate-float size-full'])
                </div>
            </div>
            <div class="mx-auto max-w-page px-4 py-20 sm:px-6 sm:py-28 lg:py-36">
                <div class="max-w-3xl">
                    <p class="animate-rise inline-flex items-center gap-3 rounded-full bg-white/10 py-1.5 ps-1.5 pe-4 text-xs font-bold tracking-wider text-gold-300 uppercase ring-1 ring-white/20 backdrop-blur">
                        <img src="/brand/logo-128.png" alt="" width="32" height="32" class="size-8 rounded-full bg-white">
                        {{ __('site.office') }}
                    </p>
                    <p class="heading-display animate-rise mt-6 text-white [--rise-delay:120ms]">{{ __('site.name') }}</p>
                    <p class="animate-rise mt-5 max-w-prose text-lg text-white/85 [--rise-delay:240ms] sm:text-xl">{{ __('site.description') }}</p>
                    <p class="animate-rise mt-9 flex flex-wrap gap-3 [--rise-delay:360ms]">
                        <x-ui.button :href="route('membership.apply')" variant="gold" size="lg" class="shadow-lg shadow-gold-500/20">{{ __('nav.join') }}</x-ui.button>
                        <x-ui.button :href="route('news.index')" variant="light" size="lg" class="backdrop-blur">{{ __('nav.news') }}</x-ui.button>
                        <x-ui.button :href="route('leaders.index')" variant="light" size="lg" class="backdrop-blur">{{ __('leaders.title') }}</x-ui.button>
                    </p>
                </div>
            </div>
            <a href="#home-after-hero" class="animate-float absolute start-1/2 bottom-6 hidden -translate-x-1/2 rounded-full p-2 text-white/70 ring-1 ring-white/25 hover:bg-white/10 hover:text-white sm:inline-flex rtl:translate-x-1/2">
                <span class="sr-only">{{ __('homepage.scroll_down') }}</span>
                <svg aria-hidden="true" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="m6 9 6 6 6-6"/></svg>
            </a>
            <p class="absolute end-4 bottom-3 text-[11px] text-white/60"><x-harar-credit name="gate" /></p>
        </div>
        <span id="home-after-hero"></span>
    </section>
@endif
