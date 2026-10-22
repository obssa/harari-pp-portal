@props(['type' => null, 'lead' => false])
@php
    // ADR-0065: a story without a photo still gets a picture, in its type's colour, with the party's mark.
    $ground = match ($type?->tone()) {
        'accent' => 'from-accent-700 to-accent-900',
        'magenta' => 'from-magenta-600 to-magenta-700',
        'ink' => 'from-ink-brand to-primary-950',
        'gold' => 'from-gold-500 to-accent-600',
        default => 'from-primary-800 to-primary-950',
    };
@endphp
<div aria-hidden="true" {{ $attributes->class(['relative flex size-full items-center justify-center overflow-hidden bg-linear-135', $ground]) }}>
    @include('pages.home._plate', ['class' => 'absolute -end-12 -bottom-16 size-56 opacity-40 transition-transform duration-700 group-hover:rotate-12'])
    @include('pages.home._plate', ['class' => 'absolute -start-10 -top-12 size-32 opacity-20'])
    <span class="relative flex flex-col items-center gap-4">
        <span @class(['flex items-center justify-center rounded-full bg-white shadow-xl ring-4 ring-white/25', 'size-16' => ! $lead, 'size-28' => $lead])>
            <img src="{{ $lead ? '/brand/logo-256.png' : '/brand/logo-128.png' }}" alt="" width="112" height="112" @class(['rounded-full', 'size-14' => ! $lead, 'size-24' => $lead])>
        </span>
        @if ($lead)
            <span @class(['text-sm font-extrabold tracking-[0.18em] uppercase', 'text-ink-brand/80' => $type?->tone() === 'gold', 'text-white/90' => $type?->tone() !== 'gold'])>{{ __('site.name') }}</span>
        @endif
    </span>
</div>
