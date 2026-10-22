@props(['title' => null, 'description' => null, 'alternates' => null, 'image' => null, 'type' => 'website', 'jsonLd' => []])
@php
    $locale = app()->getLocale();
    $ethiopic = in_array($locale, ['am', 'har'], true);
    try {
        $hotline = filled(app(\App\Settings\SiteSettings::class)->hotline) ? app(\App\Settings\SiteSettings::class)->hotline : null;
    } catch (\Throwable) {
        $hotline = null;
    }
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}{{ __('site.name') }}</title>
    <meta name="description" content="{{ $description ?? __('site.description') }}">
    <meta name="theme-color" content="#083a5c">
    <link rel="icon" href="/favicon.ico" sizes="48x48">
    <link rel="icon" href="/favicon-96.png" type="image/png" sizes="96x96">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="preload" href="/fonts/portal/inter-latin-wght-v5.woff2" as="font" type="font/woff2" crossorigin>
    @if ($ethiopic)
        <link rel="preload" href="/fonts/portal/noto-sans-ethiopic-wght-v5.woff2" as="font" type="font/woff2" crossorigin>
    @endif
    <x-locale-meta :alternates="$alternates" />
    <x-seo :title="$title ?? __('site.name')" :description="$description ?? __('site.description')" :image="$image ?? app(\App\Domain\Content\Support\OgImage::class)->url('site-'.app()->getLocale(), __('site.name'), __('site.office'))" :type="$type" :json-ld="$jsonLd" />
    {{-- ADR-0040: a cookieless page view count; the homepage carousel adds its own small script (ADR-0070). --}}
    @vite(['resources/css/app.css', 'resources/js/beacon.js'])
</head>
<body class="flex min-h-screen flex-col bg-canvas text-ink antialiased">
    <a href="#content" class="sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-50 focus:rounded-control focus:bg-primary-700 focus:px-4 focus:py-2 focus:text-white">
        {{ __('nav.skip') }}
    </a>

    {{-- ADR-0062/0074: the slim dark top bar: both calendars and the hotline from tablets up, the languages and the member portal. --}}
    <div class="bg-ink-brand text-[13px] text-white/85 sm:text-sm">
        <div class="mx-auto flex max-w-page items-center justify-between gap-x-6 px-4 py-1 sm:px-6 sm:py-1.5">
            <div class="hidden items-center gap-x-5 md:flex">
                <p class="flex items-center gap-2">
                    <svg aria-hidden="true" class="size-4 text-gold-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                    <span class="sr-only">{{ __('calendar.today') }}: </span><x-date :date="now()" />
                </p>
                @if ($hotline)
                    <p class="flex items-center gap-2">
                        <svg aria-hidden="true" class="size-4 text-gold-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/></svg>
                        <span>{{ __('footer.hotline') }}</span>
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $hotline) }}" class="font-semibold text-white">{{ $hotline }}</a>
                    </p>
                @endif
            </div>
            <div class="flex min-w-0 flex-1 items-center justify-between gap-x-3 md:flex-none md:justify-end">
                <div class="-ms-2.5 min-w-0 overflow-x-auto [scrollbar-width:none] md:ms-0"><x-language-switcher :alternates="$alternates" /></div>
                <span aria-hidden="true" class="hidden h-4 w-px bg-white/20 md:block"></span>
                <a href="{{ route('member.sign-in') }}" class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2 py-1 font-semibold text-gold-400 hover:bg-white/10 hover:text-gold-300">
                    <svg aria-hidden="true" class="size-[1.1rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                    <span class="sr-only sm:not-sr-only">{{ __('nav.member_login') }}</span>
                </a>
            </div>
        </div>
    </div>

    {{-- ADR-0074: one clean row: the party's mark and name, then search, the call to join and (below desktop) the menu.
         On phones the row stays pinned at the top while reading. --}}
    <header class="sticky top-0 z-30 border-b border-line bg-surface/90 backdrop-blur-md supports-[backdrop-filter]:bg-surface/80 lg:static lg:bg-surface lg:backdrop-blur-none">
        <div class="mx-auto flex h-16 max-w-page items-center justify-between gap-3 px-4 sm:px-6 lg:h-[4.75rem]">
            <a href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif class="flex min-w-0 items-center gap-3 rounded-control no-underline">
                <img src="/brand/logo-128.png" alt="" width="56" height="56" class="size-11 shrink-0 rounded-full bg-white ring-1 ring-line lg:size-[3.25rem]">
                <span class="min-w-0 leading-tight">
                    <span class="block max-w-[10.5rem] text-[0.95rem] leading-[1.15] font-extrabold tracking-tight text-ink sm:max-w-none sm:text-lg">{{ __('site.name') }}</span>
                    <span class="hidden truncate text-[13px] text-ink-muted sm:block">{{ __('site.office') }}</span>
                </span>
            </a>
            <div class="flex shrink-0 items-center gap-1.5 sm:gap-2">
                {{-- The search field opens under the header; name="site-nav" closes any open menu panel. The search page has its own. --}}
                @unless (request()->routeIs('search'))
                <details name="site-nav" class="group/search">
                    <summary class="inline-flex size-10 cursor-pointer list-none items-center justify-center gap-2 rounded-full text-ink transition-colors hover:bg-surface-muted group-open/search:bg-surface-muted lg:h-11 lg:w-60 lg:justify-start lg:border lg:border-line lg:bg-surface-muted/60 lg:px-4 lg:text-ink-muted lg:hover:border-primary-600/40 [&::-webkit-details-marker]:hidden">
                        <svg aria-hidden="true" class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                        <span class="sr-only lg:not-sr-only lg:truncate lg:text-sm">{{ __('search.short') }}</span>
                    </summary>
                    <div class="absolute inset-x-0 top-full z-40 border-t-3 border-gold-500 bg-surface shadow-2xl">
                        <form method="get" action="{{ route('search') }}" role="search" class="mx-auto flex max-w-page gap-2 px-4 py-5 sm:px-6 sm:py-6">
                            <label for="site-search" class="sr-only">{{ __('search.label') }}</label>
                            <input id="site-search" type="search" name="q" maxlength="100" minlength="2" required placeholder="{{ __('search.placeholder') }}"
                                   class="min-w-0 flex-1 rounded-full border border-line bg-surface px-5 py-3 text-base focus:border-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-600/30">
                            <button type="submit" class="rounded-full bg-ink-brand px-6 py-3 font-bold text-white hover:bg-primary-900">{{ __('search.button') }}</button>
                        </form>
                    </div>
                </details>
                @endunless
                <a href="{{ route('membership.apply') }}" class="group/join inline-flex h-10 items-center gap-2 rounded-full bg-gold-500 px-4 text-sm font-bold text-ink-brand shadow-sm shadow-gold-500/30 transition-colors hover:bg-gold-400 lg:h-11 lg:px-5 lg:text-[15px]">
                    <span class="sm:hidden">{{ __('nav.join_short') }}</span>
                    <span class="hidden sm:inline">{{ __('nav.join') }}</span>
                    <svg aria-hidden="true" class="hidden size-4 transition-transform group-hover/join:translate-x-0.5 sm:block rtl:-scale-x-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
                <div class="lg:hidden"><x-site-nav variant="drawer" /></div>
            </div>
        </div>
        <div class="hidden border-t border-line lg:block">
            <div class="mx-auto max-w-page px-4 sm:px-6"><x-site-nav /></div>
        </div>
    </header>

    <main id="content" tabindex="-1" class="flex-1 focus:outline-none">
        {{ $slot }}
    </main>

    <x-site-footer />
</body>
</html>
