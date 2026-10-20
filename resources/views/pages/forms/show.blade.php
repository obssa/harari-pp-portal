<x-layouts.public :title="$translation->title" :description="$translation->intro" :alternates="$alternates">
    <div class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <div class="grid gap-10 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <h1 class="heading-1">{{ $translation->title }}</h1>
                @if (filled($translation->intro))
                    <p class="mt-3 max-w-prose whitespace-pre-line text-lg text-ink-muted">{{ $translation->intro }}</p>
                @endif
                <x-forms.public-form :form="$form" :translation="$translation" :turnstile-key="$turnstileKey" class="mt-8" />
            </div>
            <aside class="space-y-3 text-sm text-ink-muted" aria-label="{{ __('forms.after_title') }}">
                <h2 class="heading-3 text-ink">{{ __('forms.after_title') }}</h2>
                <p>{{ __('forms.after', ['days' => $form->response_days]) }}</p>
                <p><a href="{{ route('tickets.lookup') }}" class="text-link underline-offset-4 hover:underline">{{ __('forms.lookup_link') }}</a></p>
            </aside>
        </div>
    </div>
</x-layouts.public>
