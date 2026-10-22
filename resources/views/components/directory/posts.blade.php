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
                <li><x-news-card :post="$post" :shown="$shown" /></li>
            @endforeach
        </ul>
    </section>
@endif
