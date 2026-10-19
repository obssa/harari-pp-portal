<a href="{{ route('offices.show', ['locale' => $shown->locale, 'slug' => $shown->slug]) }}" class="font-semibold underline-offset-4 hover:underline">{{ $shown->getAttribute('name') }}</a>
<span class="ms-2 text-xs text-ink-muted">{{ $office->type->label() }}</span>
@if ($head)
    <span class="block text-sm text-ink-muted">{{ __('structure.headed_by', ['name' => $head->getAttribute('name')]) }}</span>
@endif
