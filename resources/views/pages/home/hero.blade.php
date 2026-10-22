@use('App\Domain\Content\Queries\PublicPosts')
@php
    $locale = app()->getLocale();
    $slides = [];
    foreach ($data as $post) {
        $shown = PublicPosts::shown($post, $locale);
        if ($shown !== null) {
            $slides[] = ['post' => $post, 'translation' => $shown];
        }
    }
@endphp
{{-- ADR-0062: featured stories on night blue, with a Harari basket plate; plaster around them. --}}
<section aria-label="{{ $section->heading() }}" class="bg-surface-muted">
    @if ($slides === [])
        {{-- ADR-0065/0066: Harar Gate behind the welcome, night blue over it, basket plates turning slowly. --}}
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
    @else
        <div class="mx-auto max-w-page px-4 py-8 sm:px-6 sm:py-10">
            <ul class="flex snap-x snap-mandatory gap-4 overflow-x-auto scroll-smooth">
                @foreach ($slides as $index => $slide)
                    @php $url = route('news.show', ['locale' => $slide['translation']->locale, 'slug' => $slide['translation']->slug]); @endphp
                    <li id="slide-{{ $index + 1 }}" lang="{{ $slide['translation']->locale }}" class="w-full shrink-0 snap-start">
                        <article class="relative grid overflow-hidden rounded-[1.25rem] bg-primary-900 text-white lg:min-h-[30rem] lg:grid-cols-[1.1fr_1fr]">
                            @include('pages.home._plate', ['class' => 'pointer-events-none absolute -end-16 -top-16 size-48 opacity-30 sm:size-72 lg:start-[46%] lg:end-auto'])
                            <div class="relative order-2 flex flex-col justify-end gap-4 p-6 sm:p-10 lg:order-1">
                                <p class="flex flex-wrap items-center gap-3 text-sm text-primary-100">
                                    <x-ui.badge :tone="$slide['post']->type->tone()">{{ $slide['post']->type->label() }}</x-ui.badge>
                                    @if ($slide['translation']->locale !== $locale)
                                        <x-ui.badge>{{ __('news.in_language', ['language' => \App\Domain\Translation\Support\Locales::nativeName($slide['translation']->locale)]) }}</x-ui.badge>
                                    @endif
                                    @if ($slide['translation']->published_at)
                                        <x-date :date="$slide['translation']->published_at" />
                                    @endif
                                </p>
                                @if ($slide['translation']->kicker)
                                    <p class="text-xs font-bold tracking-wider text-gold-400 uppercase">{{ $slide['translation']->kicker }}</p>
                                @endif
                                <h2 class="heading-1 text-white">
                                    <a href="{{ $url }}" class="underline-offset-4 hover:underline">{{ $slide['translation']->title }}</a>
                                </h2>
                                @if ($slide['translation']->excerpt)
                                    <p class="max-w-prose text-lg text-primary-100">{{ $slide['translation']->excerpt }}</p>
                                @endif
                                <p class="mt-2"><x-ui.button :href="$url" variant="gold" size="lg">{{ __('homepage.read_more') }}</x-ui.button></p>
                            </div>
                            @if ($slide['post']->featuredImage)
                                <x-picture :media="$slide['post']->featuredImage" preset="landscape" sizes="(min-width: 64rem) 38rem, 100vw"
                                           :loading="$index === 0 ? 'eager' : 'lazy'" :fetchpriority="$index === 0 ? 'high' : null"
                                           class="order-1 aspect-video h-full w-full object-cover lg:order-2 lg:aspect-auto" />
                            @endif
                        </article>
                    </li>
                @endforeach
            </ul>
            @if (count($slides) > 1)
                <nav aria-label="{{ __('homepage.slides') }}" class="pt-5">
                    <ol class="flex justify-center gap-2">
                        @foreach ($slides as $index => $slide)
                            <li>
                                <a href="#slide-{{ $index + 1 }}" class="inline-flex size-9 items-center justify-center rounded-full border border-line bg-surface text-sm font-bold hover:border-gold-500 hover:bg-gold-50"
                                   aria-label="{{ __('homepage.slide', ['number' => $index + 1, 'title' => $slide['translation']->title]) }}">{{ $index + 1 }}</a>
                            </li>
                        @endforeach
                    </ol>
                </nav>
            @endif
        </div>
    @endif
</section>
