<x-layouts.public
    :title="$translation->seo_title ?: $translation->title"
    :description="$translation->seo_description ?: $translation->excerpt"
    :alternates="$alternates"
>
    @if ($page->template === 'landing' && $page->featuredImage)
        <section class="relative isolate overflow-hidden bg-primary-900 text-white">
            <x-picture :media="$page->featuredImage" preset="landscape" sizes="100vw" loading="eager" fetchpriority="high" class="absolute inset-0 -z-10 h-full w-full object-cover opacity-30" alt="" />
            <div class="mx-auto max-w-page px-4 py-20 sm:px-6">
                <h1 class="heading-1 max-w-3xl">{{ $translation->title }}</h1>
                @if ($translation->excerpt)
                    <p class="mt-4 max-w-prose text-lg text-primary-100">{{ $translation->excerpt }}</p>
                @endif
            </div>
        </section>
    @endif

    <article class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <nav aria-label="{{ __('news.breadcrumb') }}" class="text-sm text-ink-muted">
            <ol class="flex flex-wrap gap-1">
                <li><a href="{{ route('home') }}" class="hover:underline">{{ __('nav.home') }}</a> /</li>
                @foreach ($breadcrumbs as $crumb)
                    <li><a href="{{ $crumb['url'] }}" class="hover:underline">{{ $crumb['title'] }}</a> /</li>
                @endforeach
                <li aria-current="page">{{ $translation->title }}</li>
            </ol>
        </nav>

        @unless ($page->template === 'landing' && $page->featuredImage)
            <header class="mt-6 max-w-3xl">
                <h1 class="heading-1">{{ $translation->title }}</h1>
                @if ($translation->excerpt)
                    <p class="mt-4 text-lg text-ink-muted">{{ $translation->excerpt }}</p>
                @endif
            </header>
            @if ($page->featuredImage)
                <figure class="mt-8 max-w-4xl">
                    <x-picture :media="$page->featuredImage" preset="landscape" sizes="(min-width: 64rem) 56rem, 100vw" loading="eager" fetchpriority="high" class="w-full rounded-card" />
                </figure>
            @endif
        @endunless

        <div @class(['prose-content mt-8', 'max-w-prose' => $page->template !== 'wide', 'max-w-5xl' => $page->template === 'wide'])>{!! $body !!}</div>

        @if ($children->isNotEmpty())
            <section aria-labelledby="subpages" class="mt-14">
                <h2 id="subpages" class="heading-2">{{ __('pages.in_this_section') }}</h2>
                <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($children as $child)
                        <li><x-ui.card :title="$child['title']" :href="$child['url']" :heading-level="3">{{ $child['excerpt'] }}</x-ui.card></li>
                    @endforeach
                </ul>
            </section>
        @endif
    </article>
</x-layouts.public>
