@use('App\Domain\Organisation\Queries\PublicDirectory')
@php
    $locale = app()->getLocale();
@endphp
<x-layouts.public
    :title="$translation->name"
    :description="$translation->summary"
    :alternates="$alternates"
    :image="$image"
    :json-ld="$jsonLd"
>
    <article class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <nav aria-label="{{ __('news.breadcrumb') }}" class="text-sm text-ink-muted">
            <ol class="flex flex-wrap gap-1">
                <li><a href="{{ route('home') }}" class="hover:underline">{{ __('nav.home') }}</a> /</li>
                <li><a href="{{ route('offices.index') }}" class="hover:underline">{{ __('offices.title') }}</a> /</li>
                @if ($parent)
                    <li><a href="{{ route('offices.show', ['locale' => $parent->locale, 'slug' => $parent->slug]) }}" class="hover:underline">{{ $parent->getAttribute('name') }}</a> /</li>
                @endif
                <li><a href="{{ route('offices.index', ['type' => $office->type->value]) }}" class="hover:underline">{{ $office->type->label() }}</a></li>
            </ol>
        </nav>

        <header class="mt-6 max-w-3xl">
            <h1 class="heading-1">{{ $translation->name }}</h1>
            @if ($translation->summary)
                <p class="mt-4 text-lg text-ink-muted">{{ $translation->summary }}</p>
            @endif
        </header>

        @if ($office->coverImage)
            <figure class="mt-8 max-w-4xl">
                <x-picture :media="$office->coverImage" preset="photo" sizes="(min-width: 64rem) 56rem, 100vw" loading="eager" fetchpriority="high" class="w-full rounded-card" />
            </figure>
        @endif

        <div class="mt-10 grid gap-10 lg:grid-cols-3">
            <div class="lg:col-span-2">
                @if (trim($body) !== '')
                    <div class="prose-content max-w-prose">{!! $body !!}</div>
                @endif

                @if ($office->hasMap())
                    <section aria-labelledby="office-map" class="mt-10">
                        <h2 id="office-map" class="heading-3">{{ __('offices.map') }}</h2>
                        <x-map-frame :latitude="$office->latitude" :longitude="$office->longitude" :title="__('offices.map_of', ['office' => $translation->name])" />
                    </section>
                @endif
            </div>

            <aside class="space-y-8" aria-label="{{ __('offices.details') }}">
                @if ($head)
                    <section aria-labelledby="office-head">
                        <h2 id="office-head" class="text-sm font-semibold uppercase tracking-wide text-ink-muted">{{ __('offices.head') }}</h2>
                        <x-directory.person class="mt-3" :leader="$office->head" :translation="$head" />
                    </section>
                @endif
                <x-directory.contacts :phone="$office->phone" :email="$office->email" :address="$translation->address" :hours="$translation->hours" />
            </aside>
        </div>

        @if ($people->isNotEmpty())
            <section aria-labelledby="office-people" class="mt-14">
                <h2 id="office-people" class="heading-2">{{ __('offices.people') }}</h2>
                <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($people as $person)
                        @php $shown = PublicDirectory::shown($person, $locale); @endphp
                        @continue($shown === null)
                        <li><x-directory.person :leader="$person" :translation="$shown" /></li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($children->isNotEmpty())
            <section aria-labelledby="office-children" class="mt-14">
                <h2 id="office-children" class="heading-2">{{ __('offices.children') }}</h2>
                <ul class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($children as $child)
                        @php $shown = PublicDirectory::shown($child, $locale); @endphp
                        @continue($shown === null)
                        <li lang="{{ $shown->locale }}" class="rounded-card border border-line bg-surface p-4">
                            <a href="{{ route('offices.show', ['locale' => $shown->locale, 'slug' => $shown->slug]) }}" class="font-semibold underline-offset-4 hover:underline">{{ $shown->getAttribute('name') }}</a>
                            <p class="mt-1 text-xs text-ink-muted">{{ $child->type->label() }}</p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <x-directory.posts :posts="$posts" :heading="__('offices.posts')" />
    </article>
</x-layouts.public>
