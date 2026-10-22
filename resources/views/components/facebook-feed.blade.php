@props(['page', 'mode' => 'ask', 'height' => 640])
@php
    // ADR-0071: the party's Facebook page through Facebook's own page plugin. Unless an administrator
    // chose to load it at once, nothing comes from Facebook until the visitor asks (privacy, as for maps).
    $plugin = 'https://www.facebook.com/plugins/page.php?'.http_build_query([
        'href' => $page,
        'tabs' => 'timeline',
        'width' => 500,
        'height' => $height,
        'small_header' => 'false',
        'adapt_container_width' => 'true',
        'hide_cover' => 'false',
        'show_facepile' => 'false',
    ]);
    $placeholder = '<!doctype html><html lang="'.e(app()->getLocale()).'"><body style="margin:0;height:100vh;display:grid;place-items:center;'
        .'font:15px/1.55 system-ui,sans-serif;background:linear-gradient(160deg,#05253b,#0b4a75);color:#e6eef5;text-align:center">'
        .'<div style="padding:28px;max-width:340px">'
        .'<svg viewBox="0 0 24 24" width="52" height="52" fill="#fff" aria-hidden="true" style="margin:0 auto 14px;display:block"><path d="M9.101 23.691v-7.98H6.627v-3.667h2.474v-1.58c0-4.085 1.848-5.978 5.858-5.978.401 0 .955.042 1.468.103a8.68 8.68 0 0 1 1.141.195v3.325a8.623 8.623 0 0 0-.653-.036 26.805 26.805 0 0 0-.733-.009c-.707 0-1.259.096-1.675.309a1.686 1.686 0 0 0-.679.622c-.258.42-.374.995-.374 1.752v1.297h3.919l-.386 2.103-.287 1.564h-3.246v8.245C19.396 23.238 24 18.179 24 12.044c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.628 3.874 10.35 9.101 11.647Z"/></svg>'
        .'<p style="margin:0 0 6px;font-weight:700;font-size:17px;color:#fff">'.e(__('social.facebook.title')).'</p>'
        .'<p style="margin:0 0 18px">'.e(__('social.facebook.consent')).'</p>'
        .'<a href="'.e($plugin).'" target="_self" style="display:inline-block;padding:10px 20px;border-radius:999px;background:#f5b800;color:#05253b;font-weight:700;text-decoration:none">'
        .e(__('social.facebook.show')).'</a>'
        .'<p style="margin:16px 0 0"><a href="'.e($page).'" target="_blank" rel="noopener" style="color:#fff">'.e(__('social.facebook.open')).'</a></p>'
        .'</div></body></html>';
@endphp
<div {{ $attributes->class('mx-auto w-full max-w-[500px] overflow-hidden rounded-card border border-line bg-surface shadow-sm') }}>
    @if ($mode === 'auto')
        <iframe src="{{ $plugin }}" title="{{ __('social.facebook.frame_title') }}" height="{{ $height }}" loading="lazy"
                referrerpolicy="strict-origin-when-cross-origin" class="block w-full border-0" style="height: {{ $height }}px"></iframe>
    @else
        <iframe srcdoc="{{ $placeholder }}" title="{{ __('social.facebook.frame_title') }}" height="{{ $height }}"
                referrerpolicy="strict-origin-when-cross-origin" class="block w-full border-0" style="height: {{ $height }}px"></iframe>
    @endif
</div>
