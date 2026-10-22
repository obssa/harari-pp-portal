{{-- A word drawn in outline (ADR-0070). It is SVG so that the outline, not an invisible fill, is what
     carries the contrast; the plain words are given to screen readers next to it. --}}
@php $width = max(1, mb_strlen($word)) * 68; @endphp
<svg aria-hidden="true" viewBox="0 0 {{ $width }} 100" class="{{ $class ?? 'inline-block h-[0.74em] w-auto overflow-visible align-baseline' }}" focusable="false">
    <text x="2" y="86" font-size="100" font-weight="800" fill="none" stroke="currentColor" stroke-width="2.6"
          textLength="{{ $width - 4 }}" lengthAdjust="spacingAndGlyphs">{{ $word }}</text>
</svg>
