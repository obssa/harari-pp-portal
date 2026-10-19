<section aria-labelledby="home-gallery" class="mx-auto max-w-page px-4 py-12 sm:px-6">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="home-gallery" class="heading-2">{{ $section->heading() }}</h2>
        <a href="{{ route('gallery.index') }}" class="text-sm text-link underline-offset-4 hover:underline">{{ __('gallery.all') }}</a>
    </div>
    <p class="mt-2 text-ink-muted" lang="{{ $data['translation']->locale }}">
        <a href="{{ route('gallery.show', ['locale' => $data['translation']->locale, 'slug' => $data['translation']->slug]) }}" class="font-medium text-ink underline-offset-4 hover:underline">{{ $data['translation']->getAttribute('title') }}</a>
    </p>
    <ul class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ($data['items'] as $index => $item)
            <li>
                <a href="{{ route('gallery.show', ['locale' => $data['translation']->locale, 'slug' => $data['translation']->slug]) }}#photo-{{ $index + 1 }}" class="block overflow-hidden rounded-card"
                   aria-label="{{ __('gallery.open_photo', ['number' => $index + 1, 'total' => count($data['items'])]) }}">
                    <x-picture :media="$item->media" preset="square" sizes="(min-width: 64rem) 16rem, 50vw" class="aspect-square w-full object-cover" />
                </a>
            </li>
        @endforeach
    </ul>
</section>
