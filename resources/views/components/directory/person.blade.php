@props(['leader', 'translation', 'headingLevel' => 3])
{{-- ADR-0062: a person as a centred card, portrait round; the ring turns gold on hover. --}}
<article {{ $attributes->class('group relative flex h-full flex-col items-center gap-4 rounded-[1.125rem] border border-line bg-surface p-6 text-center transition-colors duration-150 hover:border-gold-500') }} lang="{{ $translation->locale }}">
    @if ($leader->portrait)
        <x-picture :media="$leader->portrait" preset="square" sizes="9rem" class="size-32 shrink-0 rounded-full object-cover ring-4 ring-surface-muted transition-[box-shadow] duration-150 group-hover:ring-gold-500" />
    @else
        <span aria-hidden="true" class="flex size-32 shrink-0 items-center justify-center rounded-full bg-surface-muted text-4xl font-extrabold text-primary-800 ring-4 ring-surface-muted transition-[box-shadow] duration-150 group-hover:ring-gold-500 dark:text-primary-200">{{ mb_substr($translation->name, 0, 1) }}</span>
    @endif
    <div class="min-w-0">
        <h{{ $headingLevel }} class="text-lg leading-snug font-bold">
            <a href="{{ route('leaders.show', ['locale' => $translation->locale, 'slug' => $translation->slug]) }}" class="underline-offset-4 group-hover:underline after:absolute after:inset-0">{{ $translation->name }}</a>
        </h{{ $headingLevel }}>
        @if ($translation->position)
            <p class="mt-1 text-sm text-ink-muted">{{ $translation->position }}</p>
        @endif
    </div>
</article>
