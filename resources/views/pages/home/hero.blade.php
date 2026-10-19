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
<section aria-label="{{ $section->heading() }}" class="border-b border-line bg-surface">
    @if ($slides === [])
        <div class="mx-auto max-w-page px-4 py-14 sm:px-6 sm:py-20">
            <x-ui.badge tone="accent">{{ __('site.office') }}</x-ui.badge>
            <p class="heading-1 mt-4 max-w-3xl">{{ __('site.name') }}</p>
            <p class="mt-4 max-w-prose text-lg text-ink-muted">{{ __('site.description') }}</p>
            <p class="mt-8 flex flex-wrap gap-3">
                <x-ui.button :href="route('news.index')">{{ __('nav.news') }}</x-ui.button>
                <x-ui.button :href="route('leaders.index')" variant="secondary">{{ __('leaders.title') }}</x-ui.button>
            </p>
        </div>
    @else
        <div class="mx-auto max-w-page">
            <ul class="flex snap-x snap-mandatory overflow-x-auto scroll-smooth">
                @foreach ($slides as $index => $slide)
                    <li id="slide-{{ $index + 1 }}" lang="{{ $slide['translation']->locale }}" class="w-full shrink-0 snap-start">
                        <article class="grid gap-8 px-4 py-10 sm:px-6 lg:grid-cols-2 lg:items-center">
                            @if ($slide['post']->featuredImage)
                                <x-picture :media="$slide['post']->featuredImage" preset="landscape" sizes="(min-width: 64rem) 36rem, 100vw"
                                           :loading="$index === 0 ? 'eager' : 'lazy'" :fetchpriority="$index === 0 ? 'high' : null" class="w-full rounded-card" />
                            @endif
                            <div>
                                @if ($slide['translation']->kicker)
                                    <p class="text-sm font-semibold uppercase tracking-wide text-accent-700 dark:text-accent-300">{{ $slide['translation']->kicker }}</p>
                                @endif
                                <h2 class="heading-1 mt-2">
                                    <a href="{{ route('news.show', ['locale' => $slide['translation']->locale, 'slug' => $slide['translation']->slug]) }}" class="underline-offset-4 hover:underline">{{ $slide['translation']->title }}</a>
                                </h2>
                                @if ($slide['translation']->excerpt)
                                    <p class="mt-4 max-w-prose text-lg text-ink-muted">{{ $slide['translation']->excerpt }}</p>
                                @endif
                                <p class="mt-6">
                                    <x-ui.button :href="route('news.show', ['locale' => $slide['translation']->locale, 'slug' => $slide['translation']->slug])">{{ __('homepage.read_more') }}</x-ui.button>
                                </p>
                            </div>
                        </article>
                    </li>
                @endforeach
            </ul>
            @if (count($slides) > 1)
                <nav aria-label="{{ __('homepage.slides') }}" class="pb-6">
                    <ol class="flex justify-center gap-2">
                        @foreach ($slides as $index => $slide)
                            <li>
                                <a href="#slide-{{ $index + 1 }}" class="inline-flex size-8 items-center justify-center rounded-full border border-line text-sm hover:bg-surface-muted"
                                   aria-label="{{ __('homepage.slide', ['number' => $index + 1, 'title' => $slide['translation']->title]) }}">{{ $index + 1 }}</a>
                            </li>
                        @endforeach
                    </ol>
                </nav>
            @endif
        </div>
    @endif
</section>
