@use('App\Domain\Organisation\Queries\PublicDirectory')
@php $locale = app()->getLocale(); @endphp
<x-layouts.public
    :title="$translation->name"
    :description="$translation->summary"
    :alternates="$alternates"
    :image="$image"
    :json-ld="$jsonLd"
>
    @php
        $ground = match ($league->accentTone()) {
            'magenta' => 'bg-magenta-700',
            'accent' => 'bg-accent-700',
            'night' => 'bg-primary-900',
            default => 'bg-primary-700',
        };
    @endphp
    <article>
        {{-- ADR-0065: the league opens in its own colour, its chair beside the name. --}}
        <section class="relative isolate overflow-hidden text-white {{ $ground }}">
            @include('pages.home._plate', ['class' => 'pointer-events-none absolute -end-28 -top-24 -z-10 size-[30rem] opacity-30'])
            <div aria-hidden="true" class="absolute inset-0 -z-10 bg-linear-to-br from-black/0 via-black/0 to-black/25"></div>
            <div class="mx-auto max-w-page px-4 py-10 sm:px-6 sm:py-14">
                <nav aria-label="{{ __('news.breadcrumb') }}" class="text-sm text-white/75">
                    <ol class="flex flex-wrap items-center gap-2">
                        <li><a href="{{ route('home') }}" class="hover:text-white hover:underline">{{ __('nav.home') }}</a></li>
                        <li class="flex items-center gap-2"><span aria-hidden="true">/</span><a href="{{ route('leagues.index') }}" class="hover:text-white hover:underline">{{ __('leagues.title') }}</a></li>
                        <li class="flex items-center gap-2"><span aria-hidden="true">/</span><span aria-current="page" class="font-semibold text-white">{{ $translation->name }}</span></li>
                    </ol>
                </nav>
                <div class="mt-10 grid items-end gap-10 lg:grid-cols-[1fr_22rem]">
                    <header>
                        @if ($league->logo)
                            <x-picture :media="$league->logo" preset="square" sizes="5rem" class="mb-6 size-20 rounded-full bg-white object-contain p-1" />
                        @endif
                        <h1 class="heading-display text-white">{{ $translation->name }}</h1>
                        @if ($translation->summary)
                            <p class="mt-5 max-w-prose text-lg text-white/85 sm:text-xl">{{ $translation->summary }}</p>
                        @endif
                        <p class="mt-8 flex flex-wrap gap-3">
                            <x-ui.button :href="route('membership.apply')" variant="gold" size="lg">{{ __('nav.join') }}</x-ui.button>
                            <x-ui.button :href="route('volunteer.create')" variant="light" size="lg">{{ __('nav.volunteer') }}</x-ui.button>
                        </p>
                    </header>
                    @if ($head)
                        <a href="{{ route('leaders.show', ['locale' => $head->locale, 'slug' => $head->slug]) }}" lang="{{ $head->locale }}"
                           class="group flex items-center gap-4 rounded-[1.25rem] bg-white/10 p-5 ring-1 ring-white/20 backdrop-blur transition hover:bg-white/15">
                            @if ($league->head?->portrait)
                                <x-picture :media="$league->head->portrait" preset="square" sizes="5rem" class="size-18 shrink-0 rounded-full object-cover ring-4 ring-gold-500" />
                            @else
                                <span aria-hidden="true" class="flex size-18 shrink-0 items-center justify-center rounded-full bg-white text-2xl font-extrabold text-ink-brand ring-4 ring-gold-500">{{ mb_substr($head->name, 0, 1) }}</span>
                            @endif
                            <span class="min-w-0">
                                <span class="block text-[11px] font-bold tracking-wider text-gold-300 uppercase">{{ __('leagues.head') }}</span>
                                <span class="mt-1 block text-lg leading-snug font-bold group-hover:underline">{{ $head->name }}</span>
                                @if ($head->position)
                                    <span class="mt-0.5 block text-sm text-white/80">{{ $head->position }}</span>
                                @endif
                            </span>
                        </a>
                    @endif
                </div>
            </div>
        </section>

        <div class="mx-auto max-w-page px-4 pt-12 pb-16 sm:px-6">
            <div class="grid gap-12 lg:grid-cols-[1fr_22rem]">
                <div class="min-w-0">
                    @if ($league->coverImage)
                        <figure class="mb-10">
                            <x-picture :media="$league->coverImage" preset="landscape" sizes="(min-width: 64rem) 52rem, 100vw" class="w-full rounded-[1.25rem]" />
                        </figure>
                    @endif
                    <h2 class="heading-2">{{ __('leagues.about') }}</h2>
                    @if (trim($body) !== '')
                        <div class="prose-content mt-6 max-w-prose">{!! $body !!}</div>
                    @elseif ($translation->summary)
                        <p class="mt-6 max-w-prose text-lg leading-relaxed">{{ $translation->summary }}</p>
                    @endif
                </div>

                <aside class="space-y-8" aria-label="{{ __('offices.details') }}">
                    @if ($people->isNotEmpty())
                        <section aria-labelledby="league-people">
                            <h2 id="league-people" class="text-xs font-bold tracking-wider text-ink-muted uppercase">{{ __('leagues.people') }}</h2>
                            <ul class="mt-4 space-y-3">
                                @foreach ($people as $person)
                                    @php $shown = PublicDirectory::shown($person, $locale); @endphp
                                    @continue($shown === null)
                                    <li><x-directory.person-row :leader="$person" :translation="$shown" /></li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                    <x-directory.contacts :phone="$league->phone" :email="$league->email" :socials="$league->socialLinks()" />
                    <section aria-labelledby="league-take-part" class="relative overflow-hidden rounded-[1.25rem] bg-surface-muted p-6">
                        @include('pages.home._plate', ['class' => 'pointer-events-none absolute -end-10 -bottom-12 size-36 opacity-25'])
                        <h2 id="league-take-part" class="relative text-lg font-extrabold">{{ __('leagues.take_part_title') }}</h2>
                        <p class="relative mt-2 text-sm text-ink-muted">{{ __('leagues.take_part_text') }}</p>
                        <p class="relative mt-4 flex flex-wrap gap-2">
                            <x-ui.button :href="route('membership.apply')" size="sm">{{ __('nav.join') }}</x-ui.button>
                            <x-ui.button :href="route('events.index')" variant="secondary" size="sm">{{ __('events.title') }}</x-ui.button>
                        </p>
                    </section>
                </aside>
            </div>

            <x-directory.posts :posts="$posts" :heading="__('leagues.posts')" />
            <x-directory.social :posts="$social" />
        </div>
    </article>
</x-layouts.public>
