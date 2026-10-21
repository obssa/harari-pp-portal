<section aria-labelledby="home-leadership" class="mx-auto max-w-page px-4 py-16 sm:px-6">
    <figure class="flex flex-wrap items-center gap-10">
        @if ($data['leader']->portrait)
            <div class="shrink-0 rounded-full bg-gold-500 p-2">
                <x-picture :media="$data['leader']->portrait" preset="square" sizes="13rem" class="size-52 rounded-full object-cover" />
            </div>
        @endif
        <div class="min-w-0 flex-1 basis-[28rem]">
            <h2 id="home-leadership" class="kicker">{{ $section->heading() }}</h2>
            <blockquote class="mt-4 text-2xl leading-snug font-bold tracking-tight sm:text-3xl" lang="{{ app()->getLocale() }}">“{{ $data['quote'] }}”</blockquote>
            <figcaption class="mt-5" lang="{{ $data['translation']->locale }}">
                <a href="{{ route('leaders.show', ['locale' => $data['translation']->locale, 'slug' => $data['translation']->slug]) }}" class="font-bold underline-offset-4 hover:underline">{{ $data['translation']->getAttribute('name') }}</a>
                @if ($data['translation']->getAttribute('position'))
                    <span class="text-ink-muted"> · {{ $data['translation']->getAttribute('position') }}</span>
                @endif
            </figcaption>
            @if ($data['link'])
                <p class="mt-4"><a href="{{ $data['link'] }}" class="font-bold text-link underline-offset-4 hover:underline">{{ __('homepage.full_message') }}</a></p>
            @endif
        </div>
    </figure>
</section>
