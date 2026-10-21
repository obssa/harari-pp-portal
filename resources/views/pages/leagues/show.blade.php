@use('App\Domain\Organisation\Queries\PublicDirectory')
@php $locale = app()->getLocale(); @endphp
<x-layouts.public
    :title="$translation->name"
    :description="$translation->summary"
    :alternates="$alternates"
    :image="$image"
    :json-ld="$jsonLd"
>
    <article>
        {{-- ADR-0062: the title of a page sits on the plaster band. --}}
        <div class="bg-surface-muted">
            <div class="mx-auto max-w-page px-4 pt-8 pb-10 sm:px-6">
                <nav aria-label="{{ __('news.breadcrumb') }}" class="text-sm text-ink-muted">
                    <ol class="flex flex-wrap gap-1">
                        <li><a href="{{ route('home') }}" class="hover:underline">{{ __('nav.home') }}</a> /</li>
                        <li><a href="{{ route('leagues.index') }}" class="hover:underline">{{ __('leagues.title') }}</a></li>
                    </ol>
                </nav>

                <header class="mt-6 flex max-w-3xl items-center gap-5">
                    @if ($league->logo)
                        <x-picture :media="$league->logo" preset="square" sizes="6rem" class="size-24 shrink-0 rounded-card object-contain" />
                    @endif
                    <div>
                        <h1 class="heading-1">{{ $translation->name }}</h1>
                        @if ($translation->summary)
                            <p class="mt-3 text-lg text-ink-muted">{{ $translation->summary }}</p>
                        @endif
                    </div>
                </header>
            </div>
        </div>
        <div class="mx-auto max-w-page px-4 pt-2 pb-12 sm:px-6">

            @if ($league->coverImage)
                <figure class="mt-8 max-w-4xl">
                    <x-picture :media="$league->coverImage" preset="landscape" sizes="(min-width: 64rem) 56rem, 100vw" loading="eager" fetchpriority="high" class="w-full rounded-card" />
                </figure>
            @endif

            <div class="mt-10 grid gap-10 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    @if (trim($body) !== '')
                        <div class="prose-content max-w-prose">{!! $body !!}</div>
                    @endif
                </div>
                <aside class="space-y-8" aria-label="{{ __('offices.details') }}">
                    @if ($head)
                        <section aria-labelledby="league-head">
                            <h2 id="league-head" class="text-sm font-semibold uppercase tracking-wide text-ink-muted">{{ __('leagues.head') }}</h2>
                            <x-directory.person class="mt-3" :leader="$league->head" :translation="$head" />
                        </section>
                    @endif
                    <x-directory.contacts :phone="$league->phone" :email="$league->email" :socials="$league->socialLinks()" />
                </aside>
            </div>

            @if ($people->isNotEmpty())
                <section aria-labelledby="league-people" class="mt-14">
                    <h2 id="league-people" class="heading-2">{{ __('leagues.people') }}</h2>
                    <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($people as $person)
                            @php $shown = PublicDirectory::shown($person, $locale); @endphp
                            @continue($shown === null)
                            <li><x-directory.person :leader="$person" :translation="$shown" /></li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <x-directory.posts :posts="$posts" :heading="__('leagues.posts')" />
            <x-directory.social :posts="$social" />
        </div>
    </article>
</x-layouts.public>
