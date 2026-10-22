<x-layouts.public
    :title="$translation->seo_title ?: $translation->title"
    :description="$translation->seo_description ?: $translation->excerpt"
    :alternates="$alternates"
>
    @if ($page->template === 'landing' && $page->featuredImage)
        {{-- ADR-0065: a landing page opens full-bleed on its photo, night blue over it for the words. --}}
        <section class="relative isolate overflow-hidden bg-primary-950 text-white">
            <x-picture :media="$page->featuredImage" preset="landscape" sizes="100vw" loading="eager" fetchpriority="high" class="absolute inset-0 -z-20 size-full object-cover" alt="" />
            <div aria-hidden="true" class="absolute inset-0 -z-10 bg-linear-to-r from-primary-950 via-primary-950/80 to-primary-900/20"></div>
            <div class="mx-auto max-w-page px-4 py-12 sm:px-6 sm:py-16">
                <nav aria-label="{{ __('news.breadcrumb') }}" class="text-sm text-white/75">
                    <ol class="flex flex-wrap items-center gap-2">
                        <li><a href="{{ route('home') }}" class="hover:text-white hover:underline">{{ __('nav.home') }}</a></li>
                        @foreach ($breadcrumbs as $crumb)
                            <li class="flex items-center gap-2"><span aria-hidden="true">/</span><a href="{{ $crumb['url'] }}" class="hover:text-white hover:underline">{{ $crumb['title'] }}</a></li>
                        @endforeach
                        <li class="flex items-center gap-2"><span aria-hidden="true">/</span><span aria-current="page" class="font-semibold text-white">{{ $translation->title }}</span></li>
                    </ol>
                </nav>
                <div class="max-w-3xl py-14 sm:py-20">
                    <h1 class="heading-display text-white">{{ $translation->title }}</h1>
                    @if ($translation->excerpt)
                        <p class="mt-5 max-w-prose text-lg text-white/85 sm:text-xl">{{ $translation->excerpt }}</p>
                    @endif
                </div>
            </div>
            @if (filled($page->featuredImage->credit))
                <p class="absolute end-4 bottom-3 text-[11px] text-white/60">{{ __('content.photo_credit', ['credit' => $page->featuredImage->credit]) }}</p>
            @endif
        </section>
    @endif

    @php $landing = $page->template === 'landing' && $page->featuredImage; @endphp
    @unless ($landing)
        <x-page-hero :title="$translation->title" :lead="$translation->excerpt"
                     :crumbs="array_map(fn (array $crumb): array => [$crumb['title'], $crumb['url']], $breadcrumbs)" />
    @endunless

    <article class="mx-auto max-w-page px-4 pt-2 pb-12 sm:px-6">
        @if (! $landing && $page->featuredImage)
            <figure class="mt-8 max-w-4xl">
                <x-picture :media="$page->featuredImage" preset="landscape" sizes="(min-width: 64rem) 56rem, 100vw" loading="eager" fetchpriority="high" class="w-full rounded-card" />
            </figure>
        @endif

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
