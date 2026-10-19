<section aria-labelledby="home-values" class="mx-auto max-w-page px-4 py-12 sm:px-6">
    <h2 id="home-values" class="heading-2">{{ $section->heading() }}</h2>
    <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($data as $item)
            <li class="rounded-card border border-line border-s-4 border-s-accent-500 bg-surface p-5">
                <h3 class="font-semibold">{{ $item['title'] }}</h3>
                @if ($item['text'] !== '')
                    <p class="mt-2 text-sm text-ink-muted">{{ $item['text'] }}</p>
                @endif
            </li>
        @endforeach
    </ul>
</section>
