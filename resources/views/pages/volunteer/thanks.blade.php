<x-layouts.public :title="__('volunteers.thanks.title')" :description="__('volunteers.form.description')">
    <div class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <div class="max-w-2xl">
            <h1 class="heading-1">{{ __('volunteers.thanks.title') }}</h1>
            <p class="mt-4 text-lg">{{ __('volunteers.thanks.text') }}</p>
            <p class="mt-8 flex flex-wrap gap-x-6 gap-y-2 font-semibold">
                <a href="{{ route('events.index') }}" class="text-link underline-offset-4 hover:underline">{{ __('volunteers.thanks.events') }}</a>
                <a href="{{ route('membership.apply') }}" class="text-link underline-offset-4 hover:underline">{{ __('membership.apply.link') }}</a>
            </p>
        </div>
    </div>
</x-layouts.public>
