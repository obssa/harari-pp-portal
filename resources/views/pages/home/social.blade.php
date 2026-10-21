<section aria-labelledby="home-social" class="bg-surface-muted">
    <div class="mx-auto max-w-page px-4 py-16 sm:px-6">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <div>
                <p class="kicker">{{ __('social.hub.title') }}</p>
                <h2 id="home-social" class="heading-2 mt-2">{{ $section->heading() }}</h2>
            </div>
            <a href="{{ route('social') }}" class="font-bold text-link underline-offset-4 hover:underline">{{ __('social.hub.all') }}</a>
        </div>
        <ul class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($data as $post)
                <li class="flex"><x-social-post :post="$post" class="w-full" /></li>
            @endforeach
        </ul>
    </div>
</section>
