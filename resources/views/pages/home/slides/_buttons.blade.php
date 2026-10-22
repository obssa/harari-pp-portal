{{-- A slide's buttons (ADR-0070): the main one in gold (orange on the white banner), the others outlined. --}}
@php $tone ??= 'dark'; $center ??= false; $margin ??= 'mt-8'; @endphp
@if ($buttons !== [])
    <p @class(['animate-rise flex flex-wrap gap-3 [--rise-delay:360ms]', $margin, 'justify-center' => $center])>
        @foreach ($buttons as $button)
            @php
                $variant = match (true) {
                    $button['style'] === 'primary' && $tone === 'banner' => 'banner',
                    $button['style'] === 'primary' => 'gold',
                    $tone === 'banner' => 'secondary',
                    default => 'light',
                };
            @endphp
            @if ($variant === 'banner')
                <a href="{{ $button['url'] }}" lang="{{ $button['lang'] }}" @if ($button['external']) rel="noopener" @endif
                   class="inline-flex items-center justify-center gap-2 rounded-full bg-linear-to-r from-[#c92f22] to-[#b8321f] px-7 py-3 text-base font-bold text-white shadow-lg shadow-[#c92f22]/25 transition-transform duration-150 hover:-translate-y-0.5 focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-[#c92f22]">{{ $button['label'] }}</a>
            @else
                <x-ui.button :href="$button['url']" :variant="$variant" size="lg" lang="{{ $button['lang'] }}" :rel="$button['external'] ? 'noopener' : null"
                             @class(['shadow-lg shadow-gold-500/20' => $variant === 'gold', 'backdrop-blur' => $variant === 'light'])>{{ $button['label'] }}</x-ui.button>
            @endif
        @endforeach
    </p>
@endif
