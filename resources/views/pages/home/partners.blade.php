<section aria-labelledby="home-partners" class="border-t border-line bg-surface">
    <div class="mx-auto flex max-w-page flex-wrap items-center gap-x-6 gap-y-3 px-4 py-10 sm:px-6">
        <h2 id="home-partners" class="text-xs font-bold tracking-wider text-ink-muted uppercase">{{ $section->heading() }}</h2>
        <ul class="flex flex-wrap gap-3 text-sm">
            @foreach ($data as $item)
                <li><a href="{{ $item['url'] }}" rel="noopener" class="inline-block rounded-full border border-line px-4 py-2 font-semibold hover:border-ink hover:bg-surface-muted">{{ $item['label'] }}</a></li>
            @endforeach
        </ul>
    </div>
</section>
