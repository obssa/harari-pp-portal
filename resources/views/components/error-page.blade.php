@props(['code', 'all' => false])
@php
    // Error pages run outside the language routes and must not depend on the
    // database: the language comes from the URL, cookie or browser.
    $locale = \App\Domain\Translation\Support\Locales::fromRequest(request());
    app()->setLocale($locale);
    $languages = $all ? \App\Domain\Translation\Support\Locales::all() : [$locale];
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __("errors.{$code}.title") }} · {{ __('site.name') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="48x48">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen flex-col bg-canvas text-ink antialiased">
    <main class="mx-auto flex w-full max-w-page flex-1 flex-col justify-center px-4 py-16 sm:px-6">
        <p class="text-sm font-semibold text-accent-700 dark:text-accent-300">{{ __('errors.code', ['code' => $code]) }}</p>
        @foreach ($languages as $language)
            <section lang="{{ $language }}" @class(['max-w-prose', 'mt-10 border-t border-line pt-8' => ! $loop->first])>
                <h1 class="heading-1 mt-2">{{ __("errors.{$code}.title", [], $language) }}</h1>
                <p class="mt-4 text-lg text-ink-muted">{{ __("errors.{$code}.body", [], $language) }}</p>
            </section>
        @endforeach
        <p class="mt-10">
            <x-ui.button :href="url('/'.$locale)">{{ __('errors.home') }}</x-ui.button>
        </p>
    </main>
    <p class="px-4 pb-6 text-center text-sm text-ink-muted sm:px-6">{{ __('site.name') }} · {{ __('site.office') }}</p>
</body>
</html>
