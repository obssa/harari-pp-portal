<figure @class(['my-8', 'lg:-mx-16' => $wide])>
    <x-picture :media="$media" preset="landscape" :sizes="$wide ? '(min-width: 64rem) 56rem, 100vw' : '(min-width: 48rem) 42rem, 100vw'" class="w-full rounded-card" />
    @if ($caption !== '' || filled($media->credit))
        <figcaption class="mt-2 text-sm text-ink-muted">
            {{ $caption }}
            @if (filled($media->credit))
                <span class="ms-1 text-xs">{{ __('content.photo_credit', ['credit' => $media->credit]) }}</span>
            @endif
        </figcaption>
    @endif
</figure>
