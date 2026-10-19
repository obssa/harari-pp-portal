<figure class="my-8">
    <ul class="grid grid-cols-2 gap-2 sm:grid-cols-3">
        @foreach ($photos as $photo)
            <li>
                <a href="{{ $photo->hasGeneratedConversion('landscape-1600') ? $photo->getUrl('landscape-1600') : $photo->getUrl() }}" class="block overflow-hidden rounded-control">
                    <x-picture :media="$photo" preset="square" sizes="(min-width: 40rem) 14rem, 50vw" class="aspect-square w-full object-cover" />
                </a>
            </li>
        @endforeach
    </ul>
    @if ($caption !== '')
        <figcaption class="mt-2 text-sm text-ink-muted">{{ $caption }}</figcaption>
    @endif
</figure>
