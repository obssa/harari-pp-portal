<p class="my-6">
    <a href="{{ $media->getUrl() }}" class="flex items-center gap-3 rounded-card border border-line bg-surface px-4 py-3 no-underline hover:border-primary-300">
        <span aria-hidden="true" class="grid h-10 w-10 shrink-0 place-items-center rounded-control bg-primary-50 text-xs font-bold uppercase text-primary-700 dark:bg-primary-950 dark:text-primary-200">{{ pathinfo($media->file_name, PATHINFO_EXTENSION) }}</span>
        <span class="min-w-0">
            <span class="block font-medium text-link">{{ $label }}</span>
            <span class="block text-xs text-ink-muted">{{ \Illuminate\Support\Number::fileSize($media->size, precision: 1) }}</span>
        </span>
    </a>
</p>
