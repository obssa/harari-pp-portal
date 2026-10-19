@props(['leader', 'translation', 'headingLevel' => 3])
<article {{ $attributes->class('group relative flex items-center gap-4 rounded-card border border-line bg-surface p-4') }} lang="{{ $translation->locale }}">
    @if ($leader->portrait)
        <x-picture :media="$leader->portrait" preset="square" sizes="5rem" class="size-20 shrink-0 rounded-full object-cover" />
    @else
        <span aria-hidden="true" class="flex size-20 shrink-0 items-center justify-center rounded-full bg-primary-50 text-2xl font-semibold text-primary-700 dark:bg-primary-950 dark:text-primary-200">{{ mb_substr($translation->name, 0, 1) }}</span>
    @endif
    <div class="min-w-0">
        <h{{ $headingLevel }} class="font-semibold leading-snug">
            <a href="{{ route('leaders.show', ['locale' => $translation->locale, 'slug' => $translation->slug]) }}" class="underline-offset-4 group-hover:underline after:absolute after:inset-0">{{ $translation->name }}</a>
        </h{{ $headingLevel }}>
        @if ($translation->position)
            <p class="mt-1 text-sm text-ink-muted">{{ $translation->position }}</p>
        @endif
    </div>
</article>
