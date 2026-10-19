@use('App\Domain\Content\Queries\PublicPosts')
<section aria-labelledby="home-ticker" class="border-b border-line bg-accent-50 dark:bg-accent-950">
    <div class="mx-auto flex max-w-page flex-wrap items-baseline gap-x-4 gap-y-1 px-4 py-2 text-sm sm:px-6">
        <h2 id="home-ticker" class="font-semibold uppercase tracking-wide text-accent-800 dark:text-accent-200">{{ $section->heading() }}</h2>
        <ul class="flex flex-wrap gap-x-5 gap-y-1">
            @foreach ($data as $post)
                @php $shown = PublicPosts::shown($post, app()->getLocale()); @endphp
                @continue($shown === null)
                <li lang="{{ $shown->locale }}"><a href="{{ route('news.show', ['locale' => $shown->locale, 'slug' => $shown->slug]) }}" class="underline-offset-4 hover:underline">{{ $shown->title }}</a></li>
            @endforeach
        </ul>
    </div>
</section>
