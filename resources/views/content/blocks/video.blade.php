<figure class="my-8">
    <div class="aspect-video overflow-hidden rounded-card bg-surface-muted">
        <iframe
            src="https://www.youtube-nocookie.com/embed/{{ $id }}"
            title="{{ $title }}"
            loading="lazy"
            allow="accelerometer; encrypted-media; gyroscope; picture-in-picture"
            allowfullscreen
            referrerpolicy="strict-origin-when-cross-origin"
            class="h-full w-full border-0"
        ></iframe>
    </div>
    <figcaption class="mt-2 text-sm text-ink-muted">{{ $title }}</figcaption>
</figure>
