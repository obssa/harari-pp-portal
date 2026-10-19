@props(['title' => null, 'alternates' => null])
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}{{ __('site.name') }}</title>
    <x-locale-meta :alternates="$alternates" />
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <div class="border-b border-slate-200 bg-slate-100 text-xs text-slate-600">
        <div class="mx-auto max-w-5xl px-4 py-1.5">
            <span class="sr-only">{{ __('calendar.today') }}:</span>
            <x-date :date="now()" />
        </div>
    </div>
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-3">
            <a href="{{ route('home') }}" class="font-semibold text-blue-800">{{ __('site.name') }}</a>
            <x-language-switcher :alternates="$alternates" />
        </div>
    </header>
    <main id="content" class="mx-auto max-w-5xl px-4 py-12">
        {{ $slot }}
    </main>
</body>
</html>
