<section aria-labelledby="home-partners" class="border-t border-line bg-surface">
    <div class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <h2 id="home-partners" class="heading-3">{{ $section->heading() }}</h2>
        <ul class="mt-4 flex flex-wrap gap-3 text-sm">
            @foreach ($data as $item)
                <li><a href="{{ $item['url'] }}" rel="noopener" class="inline-block rounded-control border border-line px-3 py-1.5 hover:bg-surface-muted">{{ $item['label'] }}</a></li>
            @endforeach
        </ul>
    </div>
</section>
