@php $tints = ['bg-gold-50 text-gold-800', 'bg-primary-50 text-primary-700', 'bg-accent-50 text-accent-700', 'bg-magenta-50 text-magenta-700']; @endphp
<section aria-labelledby="home-values" class="mx-auto max-w-page px-4 py-16 sm:px-6">
    <h2 id="home-values" class="heading-2">{{ $section->heading() }}</h2>
    <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($data as $item)
            <li class="reveal flex flex-col gap-3 rounded-card border border-line bg-surface p-5 transition hover:-translate-y-1 hover:border-gold-500 hover:shadow-lg">
                <span aria-hidden="true" class="flex size-11 items-center justify-center rounded-full {{ $tints[$loop->index % 4] }}">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-dasharray="3 2"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4" stroke-dasharray="none"/></svg>
                </span>
                <h3 class="font-bold">{{ $item['title'] }}</h3>
                @if ($item['text'] !== '')
                    <p class="text-sm text-ink-muted">{{ $item['text'] }}</p>
                @endif
            </li>
        @endforeach
    </ul>
</section>
