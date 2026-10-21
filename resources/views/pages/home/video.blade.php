<section aria-labelledby="home-video" class="mx-auto max-w-page px-4 py-12 sm:px-6">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="home-video" class="heading-2">{{ $section->heading() }}</h2>
        <a href="{{ route('videos.index') }}" class="font-bold text-link underline-offset-4 hover:underline">{{ __('video.all') }}</a>
    </div>
    <div class="mt-6 max-w-3xl">
        <x-video-card :video="$data['video']" :translation="$data['translation']" :heading-level="3" />
    </div>
</section>
