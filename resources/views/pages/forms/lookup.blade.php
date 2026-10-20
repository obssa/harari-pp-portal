<x-layouts.public :title="__('forms.lookup_title')" :alternates="null">
    <div class="mx-auto max-w-xl px-4 py-10 sm:px-6">
        <h1 class="heading-1">{{ __('forms.lookup_title') }}</h1>
        <p class="mt-3 text-ink-muted">{{ __('forms.lookup_help') }}</p>
        <form method="get" action="{{ route('tickets.lookup') }}" class="mt-6 flex flex-wrap items-end gap-3">
            <div class="grow">
                <label for="reference" class="font-medium">{{ __('forms.reference') }}</label>
                <input id="reference" name="reference" type="text" value="{{ $typed }}" placeholder="{{ __('forms.reference_placeholder') }}" maxlength="20" autocomplete="off" dir="ltr" required
                       @if ($notFound) aria-invalid="true" aria-describedby="reference-error" @endif
                       class="mt-1 block w-full rounded-control border border-line bg-surface px-3 py-2 uppercase tracking-wider">
            </div>
            <button type="submit" class="rounded-control bg-primary-600 px-4 py-2 font-semibold text-white hover:bg-primary-700">{{ __('forms.lookup_button') }}</button>
        </form>
        @if ($notFound)
            <p id="reference-error" role="alert" class="mt-3 text-sm text-danger-600">{{ __('forms.lookup_not_found') }}</p>
        @endif
    </div>
</x-layouts.public>
