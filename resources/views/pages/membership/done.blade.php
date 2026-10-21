<x-layouts.public :title="__('membership.done.title')" :description="__('membership.apply.description')">
    <div class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <div class="max-w-2xl">
            <h1 class="heading-1">{{ __('membership.done.title') }}</h1>
            <p class="mt-4 text-lg">{{ __('membership.done.text') }}</p>
            <p class="mt-6 rounded-card border border-line bg-surface p-5">
                <span class="block text-sm text-ink-muted">{{ __('membership.reference') }}</span>
                <span class="mt-1 block font-mono text-2xl font-semibold tracking-wider" dir="ltr">{{ $reference }}</span>
            </p>
            <p class="mt-4 text-ink-muted">{{ __('membership.done.keep') }}</p>
            <p class="mt-8">
                <a href="{{ route('membership.status') }}" class="font-semibold text-link underline-offset-4 hover:underline">{{ __('membership.status.link') }}</a>
            </p>
        </div>
    </div>
</x-layouts.public>
