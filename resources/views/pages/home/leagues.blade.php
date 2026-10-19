@use('App\Domain\Content\Queries\PublicPosts')
@use('App\Domain\Organisation\Queries\PublicDirectory')
@php $locale = app()->getLocale(); @endphp
<section aria-labelledby="home-leagues" class="mx-auto max-w-page px-4 py-12 sm:px-6">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="home-leagues" class="heading-2">{{ $section->heading() }}</h2>
        <a href="{{ route('leagues.index') }}" class="text-sm text-link underline-offset-4 hover:underline">{{ __('leagues.title') }}</a>
    </div>
    <ul class="mt-6 grid gap-6 sm:grid-cols-2">
        @foreach ($data as $item)
            @php
                $shown = PublicDirectory::shown($item['league'], $locale);
                $post = $item['post'] !== null ? PublicPosts::shown($item['post'], $locale) : null;
            @endphp
            @continue($shown === null)
            <li lang="{{ $shown->locale }}">
                <x-ui.card :title="$shown->getAttribute('name')" :href="route('leagues.show', ['locale' => $shown->locale, 'slug' => $shown->slug])" :media="$item['league']->coverImage ?? $item['league']->logo">
                    {{ $shown->getAttribute('summary') }}
                    @if ($post)
                        <x-slot:meta>{{ __('homepage.latest') }}: <span lang="{{ $post->locale }}">{{ $post->title }}</span></x-slot:meta>
                    @endif
                </x-ui.card>
            </li>
        @endforeach
    </ul>
</section>
