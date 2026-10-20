<section aria-labelledby="home-social" class="mx-auto max-w-page px-4 py-12 sm:px-6">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="home-social" class="heading-2">{{ $section->heading() }}</h2>
        <a href="{{ route('social') }}" class="text-sm text-link underline-offset-4 hover:underline">{{ __('social.hub.all') }}</a>
    </div>
    <ul class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($data as $post)
            <li class="flex"><x-social-post :post="$post" class="w-full" /></li>
        @endforeach
    </ul>
</section>
