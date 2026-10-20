@props(['posts'])
@if ($posts->isNotEmpty())
    <section aria-labelledby="directory-social" class="mt-12">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="directory-social" class="heading-2">{{ __('social.hub.on_social') }}</h2>
            <a href="{{ route('social') }}" class="text-sm text-link underline-offset-4 hover:underline">{{ __('social.hub.all') }}</a>
        </div>
        <ul class="mt-4 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($posts as $post)
                <li class="flex"><x-social-post :post="$post" class="w-full" /></li>
            @endforeach
        </ul>
    </section>
@endif
