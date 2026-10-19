@use('App\Domain\Content\Queries\PublicPosts')
@use('App\Domain\Translation\Support\Locales')
<section aria-labelledby="home-news" class="mx-auto max-w-page px-4 py-12 sm:px-6">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="home-news" class="heading-2">{{ $section->heading() }}</h2>
        <a href="{{ route('news.index') }}" class="text-sm text-link underline-offset-4 hover:underline">{{ __('homepage.all_news') }}</a>
    </div>
    <ul class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($data as $post)
            @php $shown = PublicPosts::shown($post, app()->getLocale()); @endphp
            @continue($shown === null)
            <li lang="{{ $shown->locale }}">
                <x-ui.card :title="$shown->title" :href="route('news.show', ['locale' => $shown->locale, 'slug' => $shown->slug])" :media="$post->featuredImage" :kicker="$shown->kicker">
                    {{ $shown->excerpt }}
                    <x-slot:badges>
                        <x-ui.badge tone="primary">{{ $post->type->label() }}</x-ui.badge>
                        @if ($shown->locale !== app()->getLocale())
                            <x-ui.badge>{{ __('news.in_language', ['language' => Locales::nativeName($shown->locale)]) }}</x-ui.badge>
                        @endif
                    </x-slot:badges>
                    <x-slot:meta><x-date :date="$shown->published_at" /></x-slot:meta>
                </x-ui.card>
            </li>
        @endforeach
    </ul>
</section>
