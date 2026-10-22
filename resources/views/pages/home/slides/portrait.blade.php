@use('App\Domain\Homepage\Models\HeroSlide')
@php
    $locale = app()->getLocale();
    [$kicker, $kickerLang] = $slide->text('kicker', $locale);
    [$title, $titleLang] = $slide->text('title', $locale);
    [$text, $textLang] = $slide->text('text', $locale);
    [$note, $noteLang] = $slide->text('note', $locale);
    [$before, $accent, $after] = HeroSlide::splitHighlight($title, $slide->text('highlight', $locale)[0]);
    $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $note) ?: [])));
    $first = $index === 0;
@endphp
{{-- ADR-0070: a message on night blue beside a framed picture of who gives it, a gold sweep along the foot. --}}
<div class="relative isolate flex w-full items-center overflow-hidden bg-primary-950 text-white {{ $height }}">
    <div aria-hidden="true" class="absolute inset-0 -z-20 bg-[radial-gradient(60rem_40rem_at_85%_30%,rgb(11_108_168/0.45),transparent_70%)]"></div>
    <svg aria-hidden="true" class="absolute inset-x-0 bottom-0 -z-10 h-24 w-full sm:h-32" viewBox="0 0 1440 160" preserveAspectRatio="none">
        <path d="M0 120 C 360 40, 900 40, 1440 110 L1440 160 L0 160 Z" fill="rgb(11 108 168 / 0.35)"/>
        <path d="M0 132 C 420 70, 940 64, 1440 126" fill="none" stroke="#f5b800" stroke-width="5"/>
    </svg>
    <div aria-hidden="true" class="pointer-events-none absolute -start-24 -top-24 -z-10 size-80 opacity-20">
        @include('pages.home._plate', ['class' => 'animate-spin-slower size-full'])
    </div>

    <div class="mx-auto grid w-full max-w-page items-center gap-7 px-4 pt-8 pb-24 sm:gap-10 sm:px-6 lg:grid-cols-[1.15fr_1fr] lg:gap-16 lg:pt-14 lg:pb-28">
        <div class="order-2 lg:order-1">
            @if ($kicker !== '')
                <p lang="{{ $kickerLang }}" class="animate-rise text-xs font-bold tracking-[0.18em] text-gold-400 uppercase">{{ $kicker }}</p>
            @endif
            <h2 lang="{{ $titleLang }}" class="animate-rise relative mt-4 text-[clamp(1.75rem,3.4vw,2.85rem)] leading-[1.15] font-extrabold tracking-tight text-white [--rise-delay:120ms]">
                <span aria-hidden="true" class="absolute -start-12 -top-3 hidden font-serif text-8xl leading-none text-gold-500/60 xl:block">“</span>
                {{ $before }}@if ($accent !== '')<span class="text-gold-400">{{ $accent }}</span>@endif{{ $after }}
            </h2>
            @if ($text !== '')
                <p lang="{{ $textLang }}" class="animate-rise mt-5 max-w-prose text-lg text-white/80 [--rise-delay:200ms]">{{ $text }}</p>
            @endif
            @if ($lines !== [])
                <p lang="{{ $noteLang }}" class="animate-rise mt-7 flex items-start gap-4 [--rise-delay:280ms]">
                    <span aria-hidden="true" class="mt-3 h-0.5 w-10 shrink-0 bg-gold-500"></span>
                    <span>
                        <span class="block text-lg font-bold text-white">{{ $lines[0] }}</span>
                        @foreach (array_slice($lines, 1) as $line)
                            <span class="block text-sm text-white/70">{{ $line }}</span>
                        @endforeach
                    </span>
                </p>
            @endif
            @include('pages.home.slides._buttons', ['buttons' => $slide->buttonsFor($locale), 'tone' => 'dark', 'center' => false])
        </div>

        <div class="animate-rise relative order-1 mx-auto w-full max-w-[12rem] [--rise-delay:80ms] sm:max-w-[20rem] lg:order-2 lg:max-w-[27rem]">
            <div aria-hidden="true" class="absolute -inset-3 rotate-3 rounded-[2rem] border-2 border-gold-400/60"></div>
            <div aria-hidden="true" class="absolute -inset-3 -rotate-2 rounded-[2rem] bg-primary-800/40"></div>
            @if ($slide->image)
                <x-picture :media="$slide->image" preset="square" sizes="(min-width: 64rem) 27rem, 20rem" :loading="$first ? 'eager' : 'lazy'" :fetchpriority="$first ? 'high' : null"
                           class="relative aspect-square w-full rounded-[1.75rem] object-cover shadow-2xl shadow-black/40 ring-1 ring-white/20" />
            @endif
            <span aria-hidden="true" class="absolute -start-4 -bottom-4 flex size-16 items-center justify-center rounded-full bg-white p-1.5 shadow-xl ring-4 ring-gold-400 sm:size-20">
                <img src="/brand/logo-128.png" alt="" width="128" height="128" class="size-full rounded-full" loading="lazy">
            </span>
        </div>
    </div>
</div>
