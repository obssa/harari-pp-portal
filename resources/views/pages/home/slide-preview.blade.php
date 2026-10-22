<x-layouts.public :title="__('homepage.carousel.preview_title')">
    <div class="bg-gold-100 text-ink-brand">
        <p class="mx-auto flex max-w-page flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2 text-sm sm:px-6">
            <strong>{{ __('homepage.carousel.preview_title') }}</strong>
            <span>{{ __('homepage.carousel.preview_note', ['status' => $slide->status()->label()]) }}</span>
        </p>
    </div>
    <x-hero-carousel :slides="$slides" :settings="$settings" :label="__('homepage.carousel.label')" />
</x-layouts.public>
