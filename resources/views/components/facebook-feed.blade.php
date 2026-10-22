@props(['page', 'mode' => 'auto', 'height' => 140])
@php
    // ADR-0071/0072/0073: the party's Facebook page through Facebook's page plugin, header only: cover, name, followers,
    // Follow and Share. That part loads where facebook.com is reachable; the plugin's own posts are left out because
    // they stay empty on many connections in Ethiopia. Posts are shown by the site itself (the social wall).
    $plugin = 'https://www.facebook.com/plugins/page.php?'.http_build_query([
        'href' => $page,
        'tabs' => '',
        'width' => 500,
        'height' => $height,
        'small_header' => 'false',
        'adapt_container_width' => 'true',
        'hide_cover' => 'true',
        'show_facepile' => 'false',
    ]);
    $placeholder = '<!doctype html><html lang="'.e(app()->getLocale()).'"><body style="margin:0;height:100vh;display:flex;align-items:center;gap:16px;padding:0 20px;box-sizing:border-box;'
        .'font:15px/1.5 system-ui,sans-serif;background:#f6f3ee;color:#334155">'
        .'<div style="flex:1"><p style="margin:0 0 4px;font-weight:700;color:#0f172a">'.e(__('social.facebook.title')).'</p>'
        .'<p style="margin:0">'.e(__('social.facebook.consent')).'</p></div>'
        .'<a href="'.e($plugin).'" target="_self" style="flex:none;padding:10px 18px;border-radius:999px;background:#1466D8;color:#fff;font-weight:700;text-decoration:none">'
        .e(__('social.facebook.show')).'</a></body></html>';
@endphp
<article {{ $attributes->class('flex flex-col overflow-hidden rounded-card border border-line bg-surface shadow-sm') }}>
    <header class="flex items-center gap-3 bg-[#1466D8] px-4 py-3 text-white">
        <x-social-icon platform="facebook" class="size-8 shrink-0" />
        <div class="min-w-0 flex-1">
            <h3 class="leading-tight font-bold">{{ \App\Support\SocialLinks::PLATFORMS['facebook'][0] }}</h3>
            <p class="truncate text-xs text-white/85">{{ __('site.name') }}</p>
        </div>
        <a href="{{ $page }}" rel="noopener me" class="shrink-0 rounded-full bg-white px-4 py-1.5 text-sm font-bold text-[#1466D8] hover:bg-white/90 focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-white">{{ __('social.action.follow') }}</a>
    </header>

    <div class="bg-surface-muted px-3 pt-3 pb-3">
        <div class="relative isolate mx-auto max-w-[500px] overflow-hidden rounded-lg bg-surface ring-1 ring-line" style="height: {{ $height }}px"
             @if ($mode === 'auto') data-facebook data-src="{{ $plugin }}" data-title="{{ __('social.facebook.frame_title') }}" @endif>
            <div aria-hidden="true" class="absolute inset-0 -z-10 flex items-center gap-3 p-3">
                <span class="size-14 shrink-0 rounded-md bg-line"></span>
                <span class="flex-1 space-y-2"><span class="block h-3.5 w-3/5 rounded bg-line"></span><span class="block h-2.5 w-1/4 rounded bg-line/70"></span><span class="mt-3 block h-6 w-24 rounded bg-line/70"></span></span>
            </div>
            @if ($mode === 'auto')
                <noscript><iframe src="{{ $plugin }}" title="{{ __('social.facebook.frame_title') }}" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" class="absolute inset-0 block size-full border-0"></iframe></noscript>
                <div data-facebook-fallback hidden class="absolute inset-0 z-10 flex items-center gap-4 bg-surface p-4">
                    <span class="inline-flex size-12 shrink-0 items-center justify-center rounded-full bg-[#1877F2] text-white"><x-social-icon platform="facebook" class="size-7" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="font-bold text-ink">{{ __('social.facebook.unreachable') }}</p>
                        <p class="mt-1 text-sm text-ink-muted">{{ __('social.facebook.unreachable_text') }}</p>
                    </div>
                </div>
            @else
                <iframe srcdoc="{{ $placeholder }}" title="{{ __('social.facebook.frame_title') }}"
                        referrerpolicy="strict-origin-when-cross-origin" class="absolute inset-0 block size-full border-0"></iframe>
            @endif
        </div>
    </div>

    <div class="relative isolate flex flex-1 flex-col justify-center gap-5 overflow-hidden p-6">
        <div aria-hidden="true" class="pointer-events-none absolute -end-14 -bottom-20 -z-10 size-56 opacity-15">
            @include('pages.home._plate', ['class' => 'size-full'])
        </div>
        <p class="max-w-prose text-ink-muted">{{ __('social.facebook.see_posts') }}</p>
        <p class="flex flex-wrap gap-3">
            <a href="{{ $page }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-full bg-[#1466D8] px-5 py-2.5 font-bold text-white hover:bg-[#1158b8]">
                {{ __('social.facebook.open') }}
                <svg aria-hidden="true" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M9 7h8v8"/></svg>
            </a>
        </p>
    </div>
</article>
@if ($mode === 'auto')
    @once
        @vite('resources/js/facebook.js')
    @endonce
@endif
