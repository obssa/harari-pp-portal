<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('site.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <main class="mx-auto flex min-h-screen max-w-3xl flex-col justify-center gap-10 px-4 py-16">
        @foreach (['en', 'am', 'om'] as $locale)
            <section lang="{{ $locale }}" @class(['border-t border-slate-200 pt-8' => ! $loop->first])>
                <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">{{ __('site.office', [], $locale) }}</p>
                <h1 @class(['mt-2 font-semibold', 'text-3xl' => $loop->first, 'text-2xl' => ! $loop->first])>{{ __('holding.title', [], $locale) }}</h1>
                <p class="mt-3 leading-relaxed text-slate-700">{{ __('holding.body', [], $locale) }}</p>
            </section>
        @endforeach
        <p class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="note">
            {{ __('holding.notice') }}
        </p>
    </main>
</body>
</html>
