@props(['title' => null, 'description' => null, 'alternates' => null])
@php
    $locale = app()->getLocale();
    $ethiopic = in_array($locale, ['am', 'har'], true);
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}{{ __('site.name') }}</title>
    <meta name="description" content="{{ $description ?? __('site.description') }}">
    <meta name="theme-color" content="#3151ed">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="preload" href="/fonts/portal/inter-latin-wght-v5.woff2" as="font" type="font/woff2" crossorigin>
    @if ($ethiopic)
        <link rel="preload" href="/fonts/portal/noto-sans-ethiopic-wght-v5.woff2" as="font" type="font/woff2" crossorigin>
    @endif
    <x-locale-meta :alternates="$alternates" />
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen flex-col bg-canvas text-ink antialiased">
    <a href="#content" class="sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-50 focus:rounded-control focus:bg-primary-700 focus:px-4 focus:py-2 focus:text-white">
        {{ __('nav.skip') }}
    </a>

    <div class="border-b border-line bg-surface-muted text-sm text-ink-muted">
        <div class="mx-auto flex max-w-page flex-wrap items-center justify-between gap-x-6 gap-y-1 px-4 py-1.5 sm:px-6">
            <p><span class="sr-only">{{ __('calendar.today') }}: </span><x-date :date="now()" /></p>
            <x-language-switcher :alternates="$alternates" />
        </div>
    </div>

    <header class="border-b border-line bg-surface">
        <div class="mx-auto flex max-w-page flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-3 rounded-control no-underline">
                <span aria-hidden="true" class="flex h-11 w-2 flex-col overflow-hidden rounded-full">
                    <span class="flex-1 bg-primary-600"></span>
                    <span class="h-3 bg-accent-500"></span>
                </span>
                <span class="leading-tight">
                    <span class="block text-base font-semibold sm:text-lg">{{ __('site.name') }}</span>
                    <span class="block text-sm text-ink-muted">{{ __('site.office') }}</span>
                </span>
            </a>
            <nav aria-label="{{ __('nav.main') }}">
                <ul class="flex flex-wrap gap-1 text-sm font-medium">
                    <li>
                        <a href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif
                           @class([
                               'inline-block rounded-control px-3 py-2 transition-colors duration-150',
                               'bg-primary-50 text-primary-800 dark:bg-primary-950 dark:text-primary-200' => request()->routeIs('home'),
                               'text-ink hover:bg-surface-muted' => ! request()->routeIs('home'),
                           ])>{{ __('nav.home') }}</a>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <main id="content" tabindex="-1" class="flex-1 focus:outline-none">
        {{ $slot }}
    </main>

    <x-site-footer />
</body>
</html>
