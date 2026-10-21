@props(['latitude', 'longitude', 'title', 'span' => 0.006])
@php
    // Nothing loads from OpenStreetMap until the reader asks (privacy, and no third-party script on the page).
    $lat = (float) $latitude;
    $lng = (float) $longitude;
    $embed = 'https://www.openstreetmap.org/export/embed.html?bbox='.urlencode(implode(',', [$lng - $span, $lat - $span * 0.66, $lng + $span, $lat + $span * 0.66]))
        .'&layer=mapnik&marker='.urlencode($lat.','.$lng);
    $link = "https://www.openstreetmap.org/?mlat={$lat}&mlon={$lng}#map=17/{$lat}/{$lng}";
    $placeholder = '<!doctype html><html lang="'.e(app()->getLocale()).'"><body style="margin:0;height:100vh;display:grid;place-items:center;'
        .'font:15px/1.5 system-ui,sans-serif;background:#f1f5f9;color:#475569;text-align:center"><div style="padding:16px">'
        .'<p style="margin:0 0 12px">'.e(__('maps.consent')).'</p>'
        .'<a href="'.e($embed).'" target="_self" style="display:inline-block;padding:8px 16px;border-radius:8px;background:#0b6ca8;color:#fff;font-weight:600;text-decoration:none">'
        .e(__('maps.show')).'</a></div></body></html>';
@endphp
<iframe srcdoc="{{ $placeholder }}" title="{{ $title }}" referrerpolicy="no-referrer"
        {{ $attributes->class('mt-4 aspect-video w-full rounded-card border border-line') }}></iframe>
<p class="mt-2 text-sm"><a href="{{ $link }}" rel="noopener" class="text-link underline-offset-4 hover:underline">{{ __('offices.larger_map') }}</a></p>
