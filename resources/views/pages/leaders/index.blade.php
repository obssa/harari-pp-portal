@use('App\Domain\Organisation\Enums\LeaderBody')
@use('App\Domain\Organisation\Queries\PublicDirectory')
@use('App\Support\Initials')
@php
    $locale = app()->getLocale();
    // ADR-0066: the head of the branch office opens the page; the groups follow, the region's government first.
    $secretariat = collect($groups)->firstWhere('body', LeaderBody::Secretariat);
    $featured = $secretariat['leaders'][0] ?? null;
    $featuredShown = $featured !== null ? PublicDirectory::shown($featured, $locale) : null;
@endphp
<x-layouts.public :title="__('leaders.title')" :description="__('leaders.description')">
    <x-page-hero :title="__('leaders.title')" :lead="__('leaders.description')" tone="night" :crumbs="[[__('nav.about'), route('structure')]]">
        @if (count($groups) > 1)
            <nav aria-label="{{ __('leaders.jump') }}">
                <ul class="flex flex-wrap gap-2">
                    @foreach ($groups as $group)
                        <li>
                            <a href="#body-{{ $group['body']->value }}" class="inline-flex items-center gap-2 rounded-full bg-white/10 py-2 ps-4 pe-2 text-sm font-bold text-white ring-1 ring-white/20 backdrop-blur transition hover:bg-white/20">
                                {{ $group['body']->label() }}
                                <span class="rounded-full bg-gold-500 px-2 py-0.5 text-xs text-ink-brand">{{ $group['leaders']->count() }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif
    </x-page-hero>

    <div class="mx-auto max-w-page px-4 pb-16 sm:px-6">
        @if ($featuredShown)
            <section aria-label="{{ __('leaders.featured') }}" class="mt-12">
                <a href="{{ route('leaders.show', ['locale' => $featuredShown->locale, 'slug' => $featuredShown->slug]) }}" lang="{{ $featuredShown->locale }}"
                   class="reveal group relative isolate flex flex-wrap items-center gap-8 overflow-hidden rounded-[1.75rem] bg-primary-900 p-8 text-white shadow-xl transition hover:shadow-2xl sm:p-10 lg:gap-12">
                    <div aria-hidden="true" class="pointer-events-none absolute -end-24 -bottom-32 -z-10 size-[26rem] opacity-40">
                        @include('pages.home._plate', ['class' => 'animate-spin-slow size-full'])
                    </div>
                    <div aria-hidden="true" class="absolute inset-0 -z-20 bg-linear-to-br from-primary-800 via-primary-900 to-primary-950"></div>
                    @if ($featured->portrait)
                        <x-picture :media="$featured->portrait" preset="square" sizes="13rem" loading="eager" class="size-44 shrink-0 rounded-full object-cover ring-6 ring-gold-500 sm:size-52" />
                    @else
                        <span aria-hidden="true" class="flex size-44 shrink-0 items-center justify-center rounded-full bg-white text-5xl font-extrabold text-ink-brand ring-6 ring-gold-500 sm:size-52">{{ Initials::of($featuredShown->name) }}</span>
                    @endif
                    <span class="flex min-w-0 flex-1 basis-80 flex-col gap-3">
                        <span class="text-xs font-bold tracking-wider text-gold-400 uppercase">{{ __('leaders.featured') }}</span>
                        <span class="text-3xl leading-tight font-extrabold tracking-tight sm:text-4xl">{{ $featuredShown->name }}</span>
                        @if ($featuredShown->position)
                            <span class="text-lg text-white/85">{{ $featuredShown->position }}</span>
                        @endif
                        @if ($featuredShown->summary)
                            <span class="max-w-prose text-white/75">{{ $featuredShown->summary }}</span>
                        @endif
                        <span class="mt-2 inline-flex items-center gap-2 font-bold text-gold-300">
                            {{ __('leaders.view_profile') }}
                            <svg aria-hidden="true" class="size-4 transition-transform group-hover:translate-x-1 rtl:-scale-x-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </span>
                    </span>
                </a>
            </section>
        @endif

        @forelse ($groups as $group)
            <section class="mt-16 scroll-mt-28" id="body-{{ $group['body']->value }}" aria-labelledby="body-{{ $group['body']->value }}-title">
                <div class="flex flex-wrap items-end justify-between gap-3 border-b border-line pb-4">
                    <div>
                        <h2 id="body-{{ $group['body']->value }}-title" class="heading-2">{{ $group['body']->label() }}</h2>
                        <p class="mt-2 max-w-prose text-ink-muted">{{ $group['body']->description() }}</p>
                    </div>
                    <span class="rounded-full bg-surface-muted px-3 py-1 text-sm font-bold text-ink-muted">{{ trans_choice('leaders.count', $group['leaders']->count(), ['count' => $group['leaders']->count()]) }}</span>
                </div>
                <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($group['leaders'] as $leader)
                        @php $shown = PublicDirectory::shown($leader, $locale); @endphp
                        @continue($shown === null)
                        <li><x-directory.person :leader="$leader" :translation="$shown" /></li>
                    @endforeach
                </ul>
            </section>
        @empty
            <p class="mt-10 rounded-card border border-line bg-surface px-5 py-8 text-center text-ink-muted">{{ __('directory.empty') }}</p>
        @endforelse

        <section aria-labelledby="leaders-structure" class="reveal relative mt-20 overflow-hidden rounded-[1.75rem] bg-surface-muted p-8 sm:p-10">
            <div aria-hidden="true" class="pointer-events-none absolute -end-16 -top-20 size-72 opacity-25">
                @include('pages.home._plate', ['class' => 'animate-spin-slower size-full'])
            </div>
            <div class="relative flex flex-wrap items-center justify-between gap-6">
                <div class="max-w-2xl">
                    <h2 id="leaders-structure" class="heading-2">{{ __('leaders.organised_title') }}</h2>
                    <p class="mt-3 text-lg text-ink-muted">{{ __('leaders.organised_text') }}</p>
                </div>
                <p class="flex flex-wrap gap-3">
                    <x-ui.button :href="route('structure')">{{ __('structure.title') }}</x-ui.button>
                    <x-ui.button :href="route('offices.index')" variant="secondary">{{ __('offices.title') }}</x-ui.button>
                </p>
            </div>
        </section>
    </div>
</x-layouts.public>
