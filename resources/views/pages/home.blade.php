<x-layouts.public :title="__('holding.title')">
    <section class="border-b border-line bg-surface">
        <div class="mx-auto max-w-page px-4 py-14 sm:px-6 sm:py-20">
            <x-ui.badge tone="accent">{{ __('site.office') }}</x-ui.badge>
            <h1 class="heading-1 mt-4 max-w-3xl">{{ __('holding.title') }}</h1>
            <p class="mt-4 max-w-prose text-lg text-ink-muted">{{ __('holding.body') }}</p>
            <p role="note" class="mt-8 max-w-prose rounded-card border border-warning-600/30 bg-warning-50 px-4 py-3 text-sm text-warning-800">
                {{ __('holding.notice') }}
            </p>
        </div>
    </section>

    <section aria-labelledby="languages" class="mx-auto max-w-page px-4 py-12 sm:px-6">
        <h2 id="languages" class="heading-2">{{ __('holding.languages') }}</h2>
        <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach (\App\Domain\Translation\Support\Locales::all() as $language)
                <li lang="{{ $language }}">
                    <a href="{{ route('home', ['locale' => $language]) }}" hreflang="{{ $language }}"
                       @if ($language === app()->getLocale()) aria-current="page" @endif
                       @class([
                           'flex h-full flex-col gap-1 rounded-card border p-5 transition-colors duration-150',
                           'border-primary-600 bg-primary-50 dark:bg-primary-950' => $language === app()->getLocale(),
                           'border-line bg-surface hover:border-primary-300' => $language !== app()->getLocale(),
                       ])>
                        <span class="text-lg font-semibold">{{ \App\Domain\Translation\Support\Locales::nativeName($language) }}</span>
                        <span class="text-sm text-ink-muted">{{ __('holding.read_in', [], $language) }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
</x-layouts.public>
