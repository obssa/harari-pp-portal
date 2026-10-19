<section aria-labelledby="home-leadership" class="mx-auto max-w-page px-4 py-12 sm:px-6">
    <h2 id="home-leadership" class="heading-2">{{ $section->heading() }}</h2>
    <figure class="mt-6 grid gap-6 rounded-card border border-line bg-surface p-6 sm:grid-cols-[8rem_1fr] sm:items-center">
        @if ($data['leader']->portrait)
            <x-picture :media="$data['leader']->portrait" preset="square" sizes="8rem" class="size-32 rounded-full object-cover" />
        @endif
        <div>
            <blockquote class="text-xl leading-relaxed" lang="{{ app()->getLocale() }}">“{{ $data['quote'] }}”</blockquote>
            <figcaption class="mt-4 text-sm" lang="{{ $data['translation']->locale }}">
                <a href="{{ route('leaders.show', ['locale' => $data['translation']->locale, 'slug' => $data['translation']->slug]) }}" class="font-semibold underline-offset-4 hover:underline">{{ $data['translation']->getAttribute('name') }}</a>
                @if ($data['translation']->getAttribute('position'))
                    <span class="block text-ink-muted">{{ $data['translation']->getAttribute('position') }}</span>
                @endif
            </figcaption>
            @if ($data['link'])
                <p class="mt-4"><a href="{{ $data['link'] }}" class="text-link underline-offset-4 hover:underline">{{ __('homepage.full_message') }}</a></p>
            @endif
        </div>
    </figure>
</section>
