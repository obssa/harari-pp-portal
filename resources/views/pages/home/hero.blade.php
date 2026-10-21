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
        <div class="relative overflow-hidden bg-primary-900 text-white">
            @include('pages.home._plate', ['class' => 'pointer-events-none absolute -end-32 -top-32 size-[32rem] opacity-90'])
            <div class="relative mx-auto max-w-page px-4 py-16 sm:px-6 sm:py-24">
                <img src="/brand/logo-256.png" alt="" width="104" height="104" class="size-26 rounded-full bg-white">
                <p class="mt-6 text-xs font-bold tracking-wider text-gold-400 uppercase">{{ __('site.office') }}</p>
                <p class="heading-1 mt-3 max-w-3xl text-white">{{ __('site.name') }}</p>
                <p class="mt-4 max-w-prose text-lg text-primary-100">{{ __('site.description') }}</p>
                <p class="mt-8 flex flex-wrap gap-3">
                    <x-ui.button :href="route('membership.apply')" variant="gold" size="lg">{{ __('nav.join') }}</x-ui.button>
                    <x-ui.button :href="route('news.index')" variant="light" size="lg">{{ __('nav.news') }}</x-ui.button>
                </p>
            </div>
        </div>
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
