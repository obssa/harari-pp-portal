@props(['posts', 'heading'])
@use('App\Domain\Content\Queries\PublicPosts')
@if ($posts->isNotEmpty())
    @php $locale = app()->getLocale(); @endphp
    <section aria-labelledby="directory-posts" {{ $attributes->class('mt-14') }}>
        <h2 id="directory-posts" class="heading-2">{{ $heading }}</h2>
        <ul class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($posts as $post)
                @php $shown = PublicPosts::shown($post, $locale); @endphp
                @continue($shown === null)
                <li lang="{{ $shown->locale }}">
                    <x-ui.card :title="$shown->title" :href="route('news.show', ['locale' => $shown->locale, 'slug' => $shown->slug])" :media="$post->featuredImage">
                        <x-slot:badges>
                            <x-ui.badge :tone="$post->type->tone()">{{ $post->type->label() }}</x-ui.badge>
                            @if ($shown->locale !== $locale)
                                <x-ui.badge>{{ __('news.in_language', ['language' => \App\Domain\Translation\Support\Locales::nativeName($shown->locale)]) }}</x-ui.badge>
                            @endif
                        </x-slot:badges>
                        <x-slot:meta><x-date :date="$shown->published_at" /></x-slot:meta>
                    </x-ui.card>
                </li>
            @endforeach
        </ul>
    </section>
@endif
