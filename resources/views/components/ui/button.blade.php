@props(['href' => null, 'variant' => 'primary', 'size' => 'md', 'type' => 'button'])
@php
    $classes = \Illuminate\Support\Arr::toCssClasses([
        'inline-flex items-center justify-center gap-2 rounded-full font-bold transition-colors duration-150 focus-visible:outline-3 disabled:opacity-50',
        'px-5 py-2.5 text-sm' => $size === 'md',
        'px-3.5 py-1.5 text-sm' => $size === 'sm',
        'px-6 py-3 text-base' => $size === 'lg',
        'bg-primary-600 text-white hover:bg-primary-700' => $variant === 'primary',
        // ADR-0062: gold is the call to action, always with ink text.
        'bg-gold-500 text-ink-brand hover:bg-gold-400' => $variant === 'gold',
        'bg-accent-600 text-white hover:bg-accent-700' => $variant === 'accent',
        'border border-ink bg-surface text-ink hover:bg-surface-muted' => $variant === 'secondary',
        'border border-white/50 text-white hover:bg-white/10' => $variant === 'light',
        'text-link hover:bg-surface-muted' => $variant === 'ghost',
    ]);
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
