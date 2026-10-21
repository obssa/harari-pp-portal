<x-layouts.public :title="__('campaigns.unsubscribe.title')">
    <div class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <div class="max-w-xl">
            <h1 class="heading-1">{{ __('campaigns.unsubscribe.title') }}</h1>
            @if ($done)
                <p role="status" class="mt-4 text-lg">{{ __('campaigns.unsubscribe.done') }}</p>
            @else
                <p class="mt-4 text-lg">{{ __('campaigns.unsubscribe.ask') }}</p>
                <form method="post" action="{{ route('campaigns.unsubscribe.post', ['token' => $token]) }}" class="mt-6">
                    <button type="submit" class="inline-flex items-center rounded-control bg-primary-600 px-5 py-2.5 font-semibold text-white hover:bg-primary-700">{{ __('campaigns.unsubscribe.button') }}</button>
                </form>
            @endif
        </div>
    </div>
</x-layouts.public>
