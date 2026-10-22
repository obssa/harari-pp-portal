@php
    $locale = app()->getLocale();
    $url = route('news.show', ['locale' => $translation->locale, 'slug' => $translation->slug]);
    $first = $index === 0;
@endphp
{{-- ADR-0070: a featured story as a slide: its photo behind night blue (the party's plates when it has none). --}}
<div lang="{{ $translation->locale }}" class="relative isolate flex w-full items-end overflow-hidden bg-primary-900 text-white lg:items-center {{ $height }}">
    @if ($post->featuredImage)
        <x-picture :media="$post->featuredImage" preset="landscape" sizes="100vw" :loading="$first ? 'eager' : 'lazy'" :fetchpriority="$first ? 'high' : null"
                   class="absolute inset-0 -z-20 size-full object-cover" />
        <div aria-hidden="true" class="absolute inset-0 -z-10 bg-linear-to-r from-primary-950 via-primary-950/85 to-primary-950/30"></div>
    @else
        <div aria-hidden="true" class="pointer-events-none absolute -end-24 top-1/2 -z-10 size-[36rem] -translate-y-1/2 opacity-25">
            @include('pages.home._plate', ['class' => 'animate-spin-slow size-full'])
        </div>
    @endif
    <div class="mx-auto w-full max-w-page px-4 pt-14 pb-28 sm:px-6 sm:pt-20">
        <article class="max-w-3xl">
            <p class="animate-rise flex flex-wrap items-center gap-3 text-sm text-primary-100">
                <x-ui.badge :tone="$post->type->tone()">{{ $post->type->label() }}</x-ui.badge>
                @if ($translation->locale !== $locale)
                    <x-ui.badge>{{ __('news.in_language', ['language' => \App\Domain\Translation\Support\Locales::nativeName($translation->locale)]) }}</x-ui.badge>
                @endif
                @if ($translation->published_at)
                    <x-date :date="$translation->published_at" />
                @endif
            </p>
            @if ($translation->kicker)
                <p class="animate-rise mt-5 text-xs font-bold tracking-wider text-gold-400 uppercase">{{ $translation->kicker }}</p>
            @endif
            <h2 class="heading-1 animate-rise mt-3 text-white [--rise-delay:120ms]">
                <a href="{{ $url }}" class="underline-offset-4 hover:underline">{{ $translation->title }}</a>
            </h2>
            @if ($translation->excerpt)
                <p class="animate-rise mt-4 max-w-prose text-lg text-primary-100 [--rise-delay:240ms]">{{ $translation->excerpt }}</p>
            @endif
            <p class="animate-rise mt-8 [--rise-delay:360ms]"><x-ui.button :href="$url" variant="gold" size="lg">{{ __('homepage.read_more') }}</x-ui.button></p>
        </article>
    </div>
</div>
