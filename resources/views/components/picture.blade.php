<picture>
    @if ($avifSrcset)
        <source type="image/avif" srcset="{{ $avifSrcset }}" sizes="{{ $sizes }}">
    @endif
    @if ($webpSrcset)
        <source type="image/webp" srcset="{{ $webpSrcset }}" sizes="{{ $sizes }}">
    @endif
    <img
        src="{{ $src }}"
        alt="{{ $alt }}"
        @if ($width) width="{{ $width }}" @endif
        @if ($height) height="{{ $height }}" @endif
        loading="{{ $loading }}"
        decoding="async"
        @if ($fetchpriority) fetchpriority="{{ $fetchpriority }}" @endif
        {{ $attributes->merge(['style' => "object-position: {$objectPosition}"]) }}
    >
</picture>
