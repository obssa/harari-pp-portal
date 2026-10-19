@props(['href' => null, 'variant' => 'primary', 'size' => 'md', 'type' => 'button'])
@php
    $classes = \Illuminate\Support\Arr::toCssClasses([
        'inline-flex items-center justify-center gap-2 rounded-control font-medium transition-colors duration-150 focus-visible:outline-3 disabled:opacity-50',
        'px-4 py-2.5 text-sm' => $size === 'md',
        'px-3 py-1.5 text-sm' => $size === 'sm',
        'px-5 py-3 text-base' => $size === 'lg',
        'bg-primary-600 text-white hover:bg-primary-700' => $variant === 'primary',
        'bg-accent-600 text-white hover:bg-accent-700' => $variant === 'accent',
        'border border-line bg-surface text-ink hover:bg-surface-muted' => $variant === 'secondary',
        'text-link hover:bg-surface-muted' => $variant === 'ghost',
    ]);
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
