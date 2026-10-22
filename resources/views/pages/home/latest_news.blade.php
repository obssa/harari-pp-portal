@use('App\Domain\Content\Queries\PublicPosts')
@php
    $locale = app()->getLocale();
    $stories = collect($data)->map(fn ($post): array => ['post' => $post, 'shown' => PublicPosts::shown($post, $locale)])
        ->filter(fn (array $item): bool => $item['shown'] !== null)->values();
@endphp
{{-- ADR-0065: the newest story leads across two columns and two rows; the rest line up beside and below it. --}}
<section aria-labelledby="home-news" class="mx-auto max-w-page px-4 py-16 sm:px-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="kicker">{{ __('homepage.news_kicker') }}</p>
            <h2 id="home-news" class="heading-2 mt-2">{{ $section->heading() }}</h2>
        </div>
        <x-ui.button :href="route('news.index')" variant="secondary">{{ __('homepage.all_news') }}</x-ui.button>
    </div>
    <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($stories as $item)
            <li @class(['sm:col-span-2 lg:row-span-2' => $loop->first])>
                <x-news-card :post="$item['post']" :shown="$item['shown']" :lead="$loop->first" />
            </li>
        @endforeach
    </ul>
</section>
