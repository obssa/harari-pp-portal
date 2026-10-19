@use('App\Domain\Translation\Support\Locales')
@php $locale = app()->getLocale(); @endphp
<x-layouts.public
    :title="$translation->seo_title ?: $translation->title"
    :description="$translation->seo_description ?: $translation->excerpt"
    :alternates="$alternates"
>
    <article class="mx-auto max-w-page px-4 py-10 sm:px-6">
        @if ($preview ?? false)
            <p role="status" class="mb-6 rounded-card border border-warning-600/30 bg-warning-50 px-4 py-3 text-sm text-warning-800">{{ __('news.preview_notice') }}</p>
        @endif
        <nav aria-label="{{ __('news.breadcrumb') }}" class="text-sm text-ink-muted">
            <ol class="flex flex-wrap gap-1">
                <li><a href="{{ route('home') }}" class="hover:underline">{{ __('nav.home') }}</a> /</li>
                <li><a href="{{ route('news.index') }}" class="hover:underline">{{ __('news.title') }}</a> /</li>
                <li><a href="{{ route('news.index', ['type' => $post->type->value]) }}" class="hover:underline">{{ $post->type->label() }}</a></li>
            </ol>
        </nav>

        <header class="mt-6 max-w-3xl">
            @if ($translation->kicker)
                <p class="text-sm font-semibold uppercase tracking-wide text-accent-700 dark:text-accent-300">{{ $translation->kicker }}</p>
            @endif
            <h1 class="heading-1 mt-2">{{ $translation->title }}</h1>
            @if ($translation->excerpt)
                <p class="mt-4 text-lg text-ink-muted">{{ $translation->excerpt }}</p>
            @endif
            <p class="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-sm text-ink-muted">
                <span><x-date :date="$translation->published_at" /></span>
                <span>{{ trans_choice('news.reading_time', $translation->reading_time, ['minutes' => $translation->reading_time]) }}</span>
            </p>
        </header>

        @if ($post->featuredImage)
            <figure class="mt-8 max-w-4xl">
                <x-picture :media="$post->featuredImage" preset="landscape" sizes="(min-width: 64rem) 56rem, 100vw" loading="eager" fetchpriority="high" class="w-full rounded-card" />
                @if (filled($post->featuredImage->credit))
                    <figcaption class="mt-2 text-xs text-ink-muted">{{ __('content.photo_credit', ['credit' => $post->featuredImage->credit]) }}</figcaption>
                @endif
            </figure>
        @endif

        <div class="prose-content mt-8 max-w-prose">{!! $body !!}</div>

        <footer class="mt-10 max-w-prose space-y-6 border-t border-line pt-6">
            @if ($post->categories->isNotEmpty() || $post->tags->isNotEmpty())
                <ul class="flex flex-wrap gap-2" aria-label="{{ __('news.topics') }}">
                    @foreach ($post->categories as $category)
                        <li><a href="{{ route('news.index', ['category' => $category->translation()?->slug]) }}"><x-ui.badge tone="primary">{{ $category->name() }}</x-ui.badge></a></li>
                    @endforeach
                    @foreach ($post->tags as $tag)
                        <li><a href="{{ route('news.index', ['tag' => $tag->translation()?->slug]) }}"><x-ui.badge>#{{ $tag->name() }}</x-ui.badge></a></li>
                    @endforeach
                </ul>
            @endif

            @php $others = array_filter($alternates, fn ($url, $l) => $l !== $locale && $post->translation($l, fallback: false)?->isLive(), ARRAY_FILTER_USE_BOTH); @endphp
            @if ($others !== [])
                <p class="text-sm">
                    {{ __('news.also_available') }}
                    @foreach ($others as $l => $url)
                        <a href="{{ $url }}" lang="{{ $l }}" hreflang="{{ $l }}" class="ms-1 font-medium text-link underline-offset-4 hover:underline">{{ Locales::nativeName($l) }}</a>
                    @endforeach
                </p>
            @endif

            @php
                $page = urlencode(url()->current());
                $text = urlencode($translation->title);
                // Brand names are the same in every language.
                $networks = [
                    'Facebook' => "https://www.facebook.com/sharer/sharer.php?u={$page}",
                    'Telegram' => "https://t.me/share/url?url={$page}&text={$text}",
                    'X' => "https://x.com/intent/post?url={$page}&text={$text}",
                    'WhatsApp' => 'https://wa.me/?text='.urlencode($translation->title.' '.url()->current()),
                ];
            @endphp
            <div>
                <h2 class="text-sm font-semibold">{{ __('news.share') }}</h2>
                <ul class="mt-2 flex flex-wrap gap-2 text-sm">
                    @foreach ($networks as $network => $shareUrl)
                        <li><a class="inline-block rounded-control border border-line px-3 py-1.5 hover:bg-surface-muted" rel="noopener" href="{{ $shareUrl }}">{{ $network }}</a></li>
                    @endforeach
                </ul>
            </div>
        </footer>

        @if ($related->isNotEmpty())
            <section aria-labelledby="related" class="mt-14">
                <h2 id="related" class="heading-2">{{ __('news.related') }}</h2>
                <ul class="mt-6 grid gap-6 sm:grid-cols-3">
                    @foreach ($related as $item)
                        @php $t = $item->translation($locale, fallback: false); @endphp
                        @continue($t === null)
                        <li>
                            <x-ui.card :title="$t->title" :href="route('news.show', ['slug' => $t->slug])" :media="$item->featuredImage" :heading-level="3">
                                <x-slot:meta><x-date :date="$t->published_at" /></x-slot:meta>
                            </x-ui.card>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </article>
</x-layouts.public>
