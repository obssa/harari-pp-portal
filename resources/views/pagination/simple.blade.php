@if ($paginator->hasPages())
    <nav aria-label="{{ __('pagination.label') }}" class="flex items-center justify-between gap-4 text-sm">
        @if ($paginator->onFirstPage())
            <span class="rounded-control border border-line px-3 py-2 text-ink-muted" aria-disabled="true">{!! __('pagination.previous') !!}</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="rounded-control border border-line px-3 py-2 hover:bg-surface-muted">{!! __('pagination.previous') !!}</a>
        @endif

        <span class="text-ink-muted">{{ __('pagination.page_of', ['current' => $paginator->currentPage(), 'last' => $paginator->lastPage()]) }}</span>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="rounded-control border border-line px-3 py-2 hover:bg-surface-muted">{!! __('pagination.next') !!}</a>
        @else
            <span class="rounded-control border border-line px-3 py-2 text-ink-muted" aria-disabled="true">{!! __('pagination.next') !!}</span>
        @endif
    </nav>
@endif
