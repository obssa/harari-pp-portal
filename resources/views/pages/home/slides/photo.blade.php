@use('App\Domain\Homepage\Models\HeroSlide')
@php
    $locale = app()->getLocale();
    [$kicker, $kickerLang] = $slide->text('kicker', $locale);
    [$title, $titleLang] = $slide->text('title', $locale);
    [$text, $textLang] = $slide->text('text', $locale);
    [$before, $accent, $after] = HeroSlide::splitHighlight($title, $slide->text('highlight', $locale)[0]);
    $center = $slide->align === 'center';
    $shade = max(0, min(95, $slide->overlay)) / 100;
    $first = $index === 0;
@endphp
{{-- ADR-0070: a photo across the slide (Harar Gate when none is chosen), night blue over it, basket plates turning. --}}
<div class="relative isolate flex w-full items-end overflow-hidden text-white lg:items-center {{ $height }}">
    @if ($slide->image)
        <x-picture :media="$slide->image" preset="landscape" sizes="100vw" :loading="$first ? 'eager' : 'lazy'" :fetchpriority="$first ? 'high' : null"
                   class="animate-zoom absolute inset-0 -z-20 size-full object-cover" />
    @else
        <x-harar-photo name="gate" sizes="100vw" :loading="$first ? 'eager' : 'lazy'" :fetchpriority="$first ? 'high' : null"
                       class="animate-zoom absolute inset-0 -z-20 size-full object-cover object-[70%_50%]" />
    @endif
    <div aria-hidden="true" class="absolute inset-0 -z-10"
         style="background: linear-gradient({{ $center ? '0deg' : '90deg' }}, rgb(5 37 59 / {{ $shade }}) 0%, rgb(5 37 59 / {{ round($shade * 0.85, 2) }}) 45%, rgb(5 37 59 / {{ round($shade * 0.3, 2) }}) 100%)"></div>
    <div aria-hidden="true" class="absolute inset-x-0 bottom-0 -z-10 h-1/3 bg-linear-to-t from-primary-950/90 to-transparent"></div>
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-[5] overflow-hidden">
        <div class="absolute -end-56 top-1/2 size-[40rem] -translate-y-1/2 opacity-[0.18] lg:-end-48">
            @include('pages.home._plate', ['class' => 'animate-spin-slow size-full'])
        </div>
        <div class="absolute -start-24 -bottom-28 size-72 opacity-20">
            @include('pages.home._plate', ['class' => 'animate-spin-slower size-full'])
        </div>
        <div class="absolute end-[6%] top-10 hidden size-20 opacity-40 lg:block">
            @include('pages.home._plate', ['class' => 'animate-float size-full'])
        </div>
    </div>
    <div class="mx-auto w-full max-w-page px-4 pt-14 pb-28 sm:px-6 sm:pt-20">
        <div @class(['max-w-3xl', 'mx-auto text-center' => $center])>
            @if ($kicker !== '')
                <p lang="{{ $kickerLang }}" class="animate-rise inline-flex items-center gap-3 rounded-full bg-white/10 py-1.5 ps-1.5 pe-4 text-xs font-bold tracking-wider text-gold-300 uppercase ring-1 ring-white/20 backdrop-blur">
                    <img src="/brand/logo-128.png" alt="" width="32" height="32" class="size-8 rounded-full bg-white">
                    {{ $kicker }}
                </p>
            @endif
            <h2 lang="{{ $titleLang }}" class="heading-display animate-rise mt-6 text-white [--rise-delay:120ms]">{{ $before }}@if ($accent !== '')<span class="text-gold-400">{{ $accent }}</span>@endif{{ $after }}</h2>
            @if ($text !== '')
                <p lang="{{ $textLang }}" @class(['animate-rise mt-5 max-w-prose text-lg text-white/85 [--rise-delay:240ms] sm:text-xl', 'mx-auto' => $center])>{{ $text }}</p>
            @endif
            @include('pages.home.slides._buttons', ['buttons' => $slide->buttonsFor($locale), 'tone' => 'dark', 'center' => $center])
        </div>
    </div>
    @if ($slide->image)
        @if (filled($slide->image->credit))
            <p class="absolute end-4 bottom-16 text-[11px] text-white/60 sm:bottom-3">{{ __('content.photo_credit', ['credit' => $slide->image->credit]) }}</p>
        @endif
    @else
        <p class="absolute end-4 bottom-16 text-[11px] text-white/60 sm:bottom-3"><x-harar-credit name="gate" /></p>
    @endif
</div>
