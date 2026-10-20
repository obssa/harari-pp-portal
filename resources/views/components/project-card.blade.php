@props(['project', 'translation', 'headingLevel' => 2])
<article {{ $attributes->class('group relative flex flex-col overflow-hidden rounded-card border border-line bg-surface') }} lang="{{ $translation->locale }}">
    @if ($project->cover)
        <x-picture :media="$project->cover" preset="landscape" sizes="(min-width: 64rem) 24rem, (min-width: 40rem) 50vw, 100vw" class="aspect-video w-full object-cover" />
    @endif
    <div class="flex flex-1 flex-col p-4">
        <p class="flex flex-wrap items-center gap-2 text-xs">
            <x-ui.badge :tone="$project->status->tone()">{{ $project->status->label() }}</x-ui.badge>
            @if ($project->sector)
                <span class="text-ink-muted">{{ $project->sector->name() }}</span>
            @endif
        </p>
        <h{{ $headingLevel }} class="mt-2 font-semibold leading-snug">
            <a href="{{ route('projects.show', ['locale' => $translation->locale, 'slug' => $translation->slug]) }}" class="underline-offset-4 group-hover:underline after:absolute after:inset-0">{{ $translation->getAttribute('title') }}</a>
        </h{{ $headingLevel }}>
        @if (filled($translation->getAttribute('location')))
            <p class="mt-1 text-sm text-ink-muted">{{ $translation->getAttribute('location') }}</p>
        @endif
        @if ($project->progress !== null)
            <div class="mt-auto pt-4">
                <x-progress-bar :value="$project->progress" />
            </div>
        @endif
    </div>
</article>
