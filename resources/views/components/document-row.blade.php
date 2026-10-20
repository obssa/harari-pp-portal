@props(['document', 'translation', 'headingLevel' => 2])
@php
    $file = $document->fileFor(app()->getLocale())?->file;
    $closed = $document->isClosed();
@endphp
<article {{ $attributes->class('group relative flex gap-4 rounded-card border border-line bg-surface p-4') }} lang="{{ $translation->locale }}">
    <span aria-hidden="true" class="grid size-12 shrink-0 place-items-center rounded-control bg-primary-50 text-xs font-bold uppercase text-primary-700 dark:bg-primary-950 dark:text-primary-200">{{ $file ? pathinfo($file->file_name, PATHINFO_EXTENSION) : '—' }}</span>
    <div class="min-w-0">
        <p class="flex flex-wrap items-center gap-2 text-xs">
            <x-ui.badge>{{ $document->type->label() }}</x-ui.badge>
            @if ($document->deadline_at)
                <x-ui.badge :tone="$closed ? 'neutral' : 'warning'">{{ $closed ? __('documents.closed') : __('documents.open') }}</x-ui.badge>
            @endif
        </p>
        <h{{ $headingLevel }} class="mt-1 font-semibold leading-snug">
            <a href="{{ route('documents.show', ['locale' => $translation->locale, 'slug' => $translation->slug]) }}" class="underline-offset-4 group-hover:underline after:absolute after:inset-0">{{ $translation->getAttribute('title') }}</a>
        </h{{ $headingLevel }}>
        <p class="mt-1 text-sm text-ink-muted">
            @if ($document->deadline_at)
                {{ __('documents.deadline') }}: <x-date :date="$document->deadline_at" :time="true" />
            @elseif ($document->year)
                {{ $document->year }}
            @endif
            @if ($file)
                · {{ \Illuminate\Support\Number::fileSize($file->size, precision: 1) }}
            @endif
        </p>
        @if (filled($translation->getAttribute('summary')))
            <p class="mt-2 text-sm text-ink-muted">{{ \Illuminate\Support\Str::limit((string) $translation->getAttribute('summary'), 200) }}</p>
        @endif
    </div>
</article>
