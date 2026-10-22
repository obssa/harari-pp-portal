@props(['leader', 'translation', 'headingLevel' => 3])
@use('App\Support\Initials')
{{-- ADR-0062/0066: a person as a centred card, portrait round; without a photo, the party's night blue with a basket plate and initials. --}}
<article {{ $attributes->class('reveal group relative flex h-full flex-col items-center gap-4 rounded-[1.25rem] border border-line bg-surface p-6 text-center transition duration-200 hover:-translate-y-1 hover:border-gold-500 hover:shadow-xl') }} lang="{{ $translation->locale }}">
    @if ($leader->portrait)
        <x-picture :media="$leader->portrait" preset="square" sizes="9rem" class="size-32 shrink-0 rounded-full object-cover ring-4 ring-surface-muted transition-[box-shadow] duration-200 group-hover:ring-gold-500" />
    @else
        <span aria-hidden="true" class="relative flex size-32 shrink-0 items-center justify-center overflow-hidden rounded-full bg-linear-135 from-primary-700 to-primary-950 text-3xl font-extrabold tracking-wide text-white ring-4 ring-surface-muted transition-[box-shadow] duration-200 group-hover:ring-gold-500">
            @include('pages.home._plate', ['class' => 'absolute -inset-3 size-[calc(100%+1.5rem)] opacity-25 transition-transform duration-700 group-hover:rotate-45'])
            <span class="relative">{{ Initials::of($translation->name) }}</span>
        </span>
    @endif
    <div class="min-w-0">
        <h{{ $headingLevel }} class="text-lg leading-snug font-bold">
            <a href="{{ route('leaders.show', ['locale' => $translation->locale, 'slug' => $translation->slug]) }}" class="underline-offset-4 group-hover:underline after:absolute after:inset-0">{{ $translation->name }}</a>
        </h{{ $headingLevel }}>
        @if ($translation->position)
            <p class="mt-1 text-sm text-ink-muted">{{ $translation->position }}</p>
        @endif
    </div>
    <span aria-hidden="true" class="mt-auto inline-flex items-center gap-1 text-sm font-bold text-primary-700 opacity-0 transition-opacity duration-200 group-hover:opacity-100 dark:text-primary-300">
        {{ __('leaders.view_profile') }}
        <svg class="size-4 rtl:-scale-x-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
    </span>
</article>
