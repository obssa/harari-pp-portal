@use('App\Domain\Organisation\Queries\PublicDirectory')
@php $locale = app()->getLocale(); @endphp
<x-layouts.public :title="__('leagues.title')" :description="__('leagues.description')">
    <x-page-hero :title="__('leagues.title')" :lead="__('leagues.description')" tone="plaster" :crumbs="[]" />
    <div class="mx-auto max-w-page px-4 pt-2 pb-12 sm:px-6">

        @if ($leagues->isEmpty())
            <p class="mt-10 rounded-card border border-line bg-surface px-5 py-8 text-center text-ink-muted">{{ __('directory.empty') }}</p>
        @else
            {{-- ADR-0065: each league in its own colour, as on the homepage. --}}
            <ul class="mt-8 grid gap-6 sm:grid-cols-2">
                @foreach ($leagues as $league)
                    @php
                        $shown = PublicDirectory::shown($league, $locale);
                        $ground = match ($league->accentTone()) {
                            'magenta' => 'bg-magenta-700',
                            'accent' => 'bg-accent-700',
                            'night' => 'bg-primary-900',
                            default => 'bg-primary-700',
                        };
                    @endphp
                    @continue($shown === null)
                    <li lang="{{ $shown->locale }}">
                        <article class="group relative flex h-full min-h-64 flex-col justify-between overflow-hidden rounded-[1.5rem] p-8 text-white transition duration-200 hover:-translate-y-0.5 hover:shadow-2xl {{ $ground }}">
                            @include('pages.home._plate', ['class' => 'pointer-events-none absolute -end-16 -top-16 size-60 opacity-30 transition-transform duration-700 group-hover:rotate-12'])
                            <div class="relative">
                                <h2 class="text-3xl font-extrabold tracking-tight">
                                    <a href="{{ route('leagues.show', ['locale' => $shown->locale, 'slug' => $shown->slug]) }}" class="after:absolute after:inset-0">{{ $shown->getAttribute('name') }}</a>
                                </h2>
                                @if (filled($shown->getAttribute('summary')))
                                    <p class="mt-3 max-w-prose text-lg text-white/85">{{ $shown->getAttribute('summary') }}</p>
                                @endif
                            </div>
                            <p class="relative mt-8 inline-flex items-center gap-2 font-bold text-gold-300">
                                {{ __('homepage.visit') }}
                                <svg aria-hidden="true" class="size-4 transition-transform group-hover:translate-x-1 rtl:-scale-x-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                            </p>
                        </article>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.public>
