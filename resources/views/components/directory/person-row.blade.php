@props(['leader', 'translation', 'role' => null])
{{-- ADR-0065: a person in a sidebar or a list: round portrait, name, position; the whole row is the link. --}}
<article {{ $attributes->class('group relative flex items-center gap-4 rounded-card border border-line bg-surface p-3 pe-4 transition-colors duration-150 hover:border-gold-500') }} lang="{{ $translation->locale }}">
    @if ($leader->portrait)
        <x-picture :media="$leader->portrait" preset="square" sizes="4rem" class="size-14 shrink-0 rounded-full object-cover ring-2 ring-surface-muted group-hover:ring-gold-500" />
    @else
        <span aria-hidden="true" class="flex size-14 shrink-0 items-center justify-center rounded-full bg-primary-900 text-lg font-extrabold text-white ring-2 ring-surface-muted group-hover:ring-gold-500">{{ mb_substr($translation->name, 0, 1) }}</span>
    @endif
    <div class="min-w-0">
        @if ($role)
            <p class="text-[11px] font-bold tracking-wider text-accent-700 uppercase dark:text-accent-300">{{ $role }}</p>
        @endif
        <h3 class="truncate font-bold leading-snug">
            <a href="{{ route('leaders.show', ['locale' => $translation->locale, 'slug' => $translation->slug]) }}" class="underline-offset-4 after:absolute after:inset-0 group-hover:underline">{{ $translation->name }}</a>
        </h3>
        @if ($translation->position)
            <p class="line-clamp-2 text-sm text-ink-muted">{{ $translation->position }}</p>
        @endif
    </div>
</article>
