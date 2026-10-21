@use('App\Domain\Content\Queries\PublicPosts')
<section aria-labelledby="home-ticker" class="border-b border-gold-200 bg-gold-50 dark:border-line dark:bg-primary-950">
    <div class="mx-auto flex max-w-page flex-wrap items-center gap-x-5 gap-y-2 px-4 py-2.5 text-sm sm:px-6">
        <h2 id="home-ticker" class="rounded-full bg-ink-brand px-3 py-1 text-xs font-extrabold tracking-wider text-gold-500 uppercase">{{ $section->heading() }}</h2>
        <ul class="flex flex-wrap gap-x-5 gap-y-1 font-semibold">
            @foreach ($data as $post)
                @php $shown = PublicPosts::shown($post, app()->getLocale()); @endphp
                @continue($shown === null)
                <li lang="{{ $shown->locale }}"><a href="{{ route('news.show', ['locale' => $shown->locale, 'slug' => $shown->slug]) }}" class="underline-offset-4 hover:underline">{{ $shown->title }}</a></li>
            @endforeach
        </ul>
    </div>
</section>
