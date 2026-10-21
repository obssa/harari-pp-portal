@props(['href' => null, 'title', 'media' => null, 'preset' => 'landscape', 'kicker' => null, 'headingLevel' => 3])
{{-- ADR-0062: cards lift to a gold edge on hover. --}}
<article {{ $attributes->class('group relative flex flex-col overflow-hidden rounded-[1.125rem] border border-line bg-surface transition-[border-color,box-shadow] duration-150 hover:border-gold-500 hover:shadow-lg') }}>
    @if ($media)
        <x-picture :media="$media" :preset="$preset" sizes="(min-width: 64rem) 25vw, (min-width: 40rem) 50vw, 100vw" class="aspect-video w-full object-cover" />
    @endif
    <div class="flex flex-1 flex-col gap-2 p-5">
        @isset($badges)
            <div class="flex flex-wrap gap-1.5">{{ $badges }}</div>
        @endisset
        @if ($kicker)
            <p class="kicker">{{ $kicker }}</p>
        @endif
        <h{{ $headingLevel }} class="text-lg leading-snug font-bold">
            @if ($href)
                <a href="{{ $href }}" class="underline-offset-4 group-hover:underline after:absolute after:inset-0">{{ $title }}</a>
            @else
                {{ $title }}
            @endif
        </h{{ $headingLevel }}>
        @if (trim((string) $slot) !== '')
            <div class="text-sm text-ink-muted">{{ $slot }}</div>
        @endif
        @isset($meta)
            <div class="mt-auto pt-2 text-xs text-ink-muted">{{ $meta }}</div>
        @endisset
    </div>
</article>
