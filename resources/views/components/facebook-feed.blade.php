@props(['page', 'mode' => 'auto', 'height' => 600, 'title' => null])
@php
    // ADR-0071/0072: the party's Facebook page through Facebook's page plugin, in the site's own frame: a branded
    // header with the Follow button, post-shaped placeholders while Facebook loads, and a way to the page itself for
    // visitors whose connection to Facebook is slow or blocked (the plugin then stays empty, which we cannot see).
    $plugin = 'https://www.facebook.com/plugins/page.php?'.http_build_query([
        'href' => $page,
        'tabs' => 'timeline',
        'width' => 500,
        'height' => $height,
        'small_header' => 'true',
        'adapt_container_width' => 'true',
        'hide_cover' => 'false',
        'show_facepile' => 'false',
    ]);
    $placeholder = '<!doctype html><html lang="'.e(app()->getLocale()).'"><body style="margin:0;height:100vh;display:grid;place-items:center;'
        .'font:15px/1.55 system-ui,sans-serif;background:#f6f3ee;color:#334155;text-align:center">'
        .'<div style="padding:28px;max-width:340px">'
        .'<span style="display:inline-grid;place-items:center;width:64px;height:64px;border-radius:999px;background:#1877F2;margin-bottom:16px">'
        .'<svg viewBox="0 0 24 24" width="36" height="36" fill="#fff" aria-hidden="true"><path d="M9.101 23.691v-7.98H6.627v-3.667h2.474v-1.58c0-4.085 1.848-5.978 5.858-5.978.401 0 .955.042 1.468.103a8.68 8.68 0 0 1 1.141.195v3.325a8.623 8.623 0 0 0-.653-.036 26.805 26.805 0 0 0-.733-.009c-.707 0-1.259.096-1.675.309a1.686 1.686 0 0 0-.679.622c-.258.42-.374.995-.374 1.752v1.297h3.919l-.386 2.103-.287 1.564h-3.246v8.245C19.396 23.238 24 18.179 24 12.044c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.628 3.874 10.35 9.101 11.647Z"/></svg></span>'
        .'<p style="margin:0 0 6px;font-weight:700;font-size:17px;color:#0f172a">'.e(__('social.facebook.title')).'</p>'
        .'<p style="margin:0 0 18px">'.e(__('social.facebook.consent')).'</p>'
        .'<a href="'.e($plugin).'" target="_self" style="display:inline-block;padding:10px 20px;border-radius:999px;background:#1466D8;color:#fff;font-weight:700;text-decoration:none">'
        .e(__('social.facebook.show')).'</a></div></body></html>';
@endphp
<article {{ $attributes->class('flex flex-col overflow-hidden rounded-card border border-line bg-surface shadow-sm') }}>
    <header class="flex items-center gap-3 bg-[#1466D8] px-4 py-3 text-white">
        <x-social-icon platform="facebook" class="size-8 shrink-0" />
        <div class="min-w-0 flex-1">
            <h3 class="leading-tight font-bold">{{ $title ?? 'Facebook' }}</h3>
            <p class="truncate text-xs text-white/85">{{ __('site.name') }}</p>
        </div>
        <a href="{{ $page }}" rel="noopener me" class="shrink-0 rounded-full bg-white px-4 py-1.5 text-sm font-bold text-[#1466D8] hover:bg-white/90 focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-white">{{ __('social.action.follow') }}</a>
    </header>

    <div class="relative isolate overflow-hidden bg-surface-muted" style="height: {{ $height }}px"
         @if ($mode === 'auto') data-facebook data-src="{{ $plugin }}" data-title="{{ __('social.facebook.frame_title') }}" @endif>
        <div aria-hidden="true" class="fb-skeleton absolute inset-0 -z-10 space-y-4 p-4">
            @foreach ([1, 2] as $card)
                <div class="rounded-xl bg-surface p-4 shadow-sm">
                    <div class="flex items-center gap-3">
                        <span class="size-10 rounded-full bg-line"></span>
                        <span class="flex-1 space-y-2"><span class="block h-3 w-2/5 rounded bg-line"></span><span class="block h-2.5 w-1/4 rounded bg-line/70"></span></span>
                    </div>
                    <span class="mt-4 block h-3 w-11/12 rounded bg-line/80"></span>
                    <span class="mt-2 block h-3 w-3/4 rounded bg-line/80"></span>
                    <span class="mt-4 block h-36 rounded-lg bg-line/60"></span>
                </div>
            @endforeach
        </div>
        @if ($mode === 'auto')
            <noscript><iframe src="{{ $plugin }}" title="{{ __('social.facebook.frame_title') }}" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" class="absolute inset-0 block size-full border-0"></iframe></noscript>
            <div data-facebook-fallback hidden class="absolute inset-0 z-10 grid place-items-center bg-surface p-6 text-center">
                <div class="max-w-sm">
                    <span class="mx-auto inline-flex size-16 items-center justify-center rounded-full bg-[#1877F2] text-white"><x-social-icon platform="facebook" class="size-9" /></span>
                    <p class="mt-4 text-lg font-bold text-ink">{{ __('social.facebook.unreachable') }}</p>
                    <p class="mt-2 text-ink-muted">{{ __('social.facebook.unreachable_text') }}</p>
                    <p class="mt-5 flex flex-wrap justify-center gap-3">
                        <a href="{{ $page }}" target="_blank" rel="noopener" class="rounded-full bg-[#1466D8] px-5 py-2.5 font-bold text-white hover:bg-[#1158b8]">{{ __('social.facebook.open') }}</a>
                        <a href="{{ route('news.index') }}" class="rounded-full border border-line px-5 py-2.5 font-bold text-ink hover:bg-surface-muted">{{ __('nav.news') }}</a>
                    </p>
                </div>
            </div>
        @else
            <iframe srcdoc="{{ $placeholder }}" title="{{ __('social.facebook.frame_title') }}"
                    referrerpolicy="strict-origin-when-cross-origin" class="absolute inset-0 block size-full border-0"></iframe>
        @endif
    </div>

    <footer class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t border-line px-4 py-3 text-sm">
        <p class="text-ink-muted">{{ __('social.facebook.slow') }}</p>
        <a href="{{ $page }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 font-bold text-[#1466D8] underline-offset-4 hover:underline">
            {{ __('social.facebook.open') }}
            <svg aria-hidden="true" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M9 7h8v8"/></svg>
        </a>
    </footer>
</article>
@if ($mode === 'auto')
    @once
        @vite('resources/js/facebook.js')
    @endonce
@endif
