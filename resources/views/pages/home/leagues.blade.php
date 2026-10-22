@use('App\Domain\Content\Queries\PublicPosts')
@use('App\Domain\Organisation\Queries\PublicDirectory')
@php
    $locale = app()->getLocale();
    // ADR-0065: each league in the colour chosen for it (the women's wing magenta, the youth wing blue).
    $grounds = ['primary' => 'bg-primary-700', 'magenta' => 'bg-magenta-700', 'accent' => 'bg-accent-700', 'night' => 'bg-primary-900'];
@endphp
<section aria-labelledby="home-leagues" class="mx-auto max-w-page px-4 py-16 sm:px-6">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="home-leagues" class="heading-2">{{ $section->heading() }}</h2>
        <a href="{{ route('leagues.index') }}" class="font-bold text-link underline-offset-4 hover:underline">{{ __('leagues.title') }}</a>
    </div>
    <ul class="mt-8 grid gap-5 sm:grid-cols-2">
        @foreach ($data as $item)
            @php
                $shown = PublicDirectory::shown($item['league'], $locale);
                $post = $item['post'] !== null ? PublicPosts::shown($item['post'], $locale) : null;
            @endphp
            @continue($shown === null)
            <li lang="{{ $shown->locale }}">
                <article class="group relative flex h-full min-h-60 flex-col justify-between gap-6 overflow-hidden rounded-[1.25rem] p-8 text-white {{ $grounds[$item['league']->accentTone()] }}">
                    @include('pages.home._plate', ['class' => 'pointer-events-none absolute -end-16 -top-16 size-56 opacity-30'])
                    <div class="relative">
                        <h3 class="text-xs font-bold tracking-wider text-gold-300 uppercase">
                            <a href="{{ route('leagues.show', ['locale' => $shown->locale, 'slug' => $shown->slug]) }}" class="after:absolute after:inset-0">{{ $shown->getAttribute('name') }}</a>
                        </h3>
                        @if ($post)
                            <p class="mt-3 text-2xl leading-snug font-extrabold" lang="{{ $post->locale }}">{{ $post->title }}</p>
                        @elseif (filled($shown->getAttribute('summary')))
                            <p class="mt-3 text-lg leading-snug font-semibold">{{ $shown->getAttribute('summary') }}</p>
                        @endif
                    </div>
                    <p class="relative font-bold group-hover:underline">{{ __('homepage.visit', ['name' => $shown->getAttribute('name')]) }} →</p>
                </article>
            </li>
        @endforeach
    </ul>
</section>
