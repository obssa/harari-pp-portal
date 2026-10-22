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
    <article>
        {{-- ADR-0062: the title of a page sits on the plaster band. --}}
        <div class="bg-surface-muted">
            <div class="mx-auto max-w-page px-4 pt-8 pb-10 sm:px-6">
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
            </div>
        </div>
        <div class="mx-auto max-w-page px-4 pt-2 pb-12 sm:px-6">

            @if ($office->coverImage)
                <figure class="mt-8 max-w-4xl">
                    <x-picture :media="$office->coverImage" preset="photo" sizes="(min-width: 64rem) 56rem, 100vw" loading="eager" fetchpriority="high" class="w-full rounded-card" />
                </figure>
            @endif

            {{-- ADR-0065: text, map and the offices below it in the main column; people and contacts beside it. --}}
            <div class="mt-10 grid gap-12 lg:grid-cols-[1fr_22rem]">
                <div class="min-w-0 space-y-12">
                    @if (trim($body) !== '')
                        <div class="prose-content max-w-prose">{!! $body !!}</div>
                    @endif

                    @if ($children->isNotEmpty())
                        <section aria-labelledby="office-children">
                            <h2 id="office-children" class="heading-2">{{ __('offices.children') }}</h2>
                            <ul class="mt-6 grid gap-3 sm:grid-cols-2">
                                @foreach ($children as $child)
                                    @php $shown = PublicDirectory::shown($child, $locale); @endphp
                                    @continue($shown === null)
                                    <li lang="{{ $shown->locale }}">
                                        <a href="{{ route('offices.show', ['locale' => $shown->locale, 'slug' => $shown->slug]) }}"
                                           class="reveal group flex h-full items-center gap-4 rounded-card border border-line bg-surface p-4 transition hover:-translate-y-0.5 hover:border-gold-500 hover:shadow-lg">
                                            <span aria-hidden="true" class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary-900 text-gold-400">
                                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block font-bold group-hover:underline">{{ $shown->getAttribute('name') }}</span>
                                                <span class="mt-0.5 block text-sm text-ink-muted">{{ $shown->getAttribute('summary') ?: $child->type->label() }}</span>
                                            </span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    @if ($office->hasMap())
                        <section aria-labelledby="office-map">
                            <h2 id="office-map" class="heading-3">{{ __('offices.map') }}</h2>
                            <x-map-frame :latitude="$office->latitude" :longitude="$office->longitude" :title="__('offices.map_of', ['office' => $translation->name])" />
                        </section>
                    @endif
                </div>

                <aside class="space-y-8" aria-label="{{ __('offices.details') }}">
                    @if ($head)
                        <section aria-labelledby="office-head">
                            <h2 id="office-head" class="text-xs font-bold tracking-wider text-ink-muted uppercase">{{ __('offices.head') }}</h2>
                            <x-directory.person-row class="mt-4" :leader="$office->head" :translation="$head" />
                        </section>
                    @endif
                    @if ($people->isNotEmpty())
                        <section aria-labelledby="office-people">
                            <h2 id="office-people" class="text-xs font-bold tracking-wider text-ink-muted uppercase">{{ __('offices.people') }}</h2>
                            <ul class="mt-4 space-y-3">
                                @foreach ($people as $person)
                                    @php $shown = PublicDirectory::shown($person, $locale); @endphp
                                    @continue($shown === null)
                                    <li><x-directory.person-row :leader="$person" :translation="$shown" /></li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                    <x-directory.contacts :phone="$office->phone" :email="$office->email" :address="$translation->address" :hours="$translation->hours" />
                </aside>
            </div>

            <x-directory.posts :posts="$posts" :heading="__('offices.posts')" />
            <x-directory.social :posts="$social" />
        </div>
    </article>
</x-layouts.public>
