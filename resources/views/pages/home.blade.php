<x-layouts.public :title="__('holding.title')">
    <section class="max-w-3xl">
        <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">{{ __('site.office') }}</p>
        <h1 class="mt-2 text-3xl font-semibold">{{ __('holding.title') }}</h1>
        <p class="mt-4 leading-relaxed text-slate-700">{{ __('holding.body') }}</p>
        <p class="mt-8 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="note">
            {{ __('holding.notice') }}
        </p>
    </section>
</x-layouts.public>
