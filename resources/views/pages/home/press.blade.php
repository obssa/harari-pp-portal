@use('App\Domain\Content\Queries\PublicPosts')
<section aria-labelledby="home-press" class="mx-auto max-w-page px-4 py-12 sm:px-6">
    <h2 id="home-press" class="heading-2">{{ $section->heading() }}</h2>
    <ul class="mt-6 divide-y divide-line rounded-card border border-line bg-surface">
        @foreach ($data as $post)
            @php $shown = PublicPosts::shown($post, app()->getLocale()); @endphp
            @continue($shown === null)
            <li lang="{{ $shown->locale }}" class="flex flex-col gap-1 p-4 sm:flex-row sm:items-baseline sm:gap-4">
                <span class="shrink-0 text-xs text-ink-muted"><x-date :date="$shown->published_at" /></span>
                <a href="{{ route('news.show', ['locale' => $shown->locale, 'slug' => $shown->slug]) }}" class="font-medium underline-offset-4 hover:underline">{{ $shown->title }}</a>
                <x-ui.badge class="sm:ms-auto">{{ $post->type->label() }}</x-ui.badge>
            </li>
        @endforeach
    </ul>
    <p class="mt-4 text-sm"><a href="{{ route('news.index', ['type' => 'press_release']) }}" class="text-link underline-offset-4 hover:underline">{{ __('homepage.all_press') }}</a></p>
</section>
