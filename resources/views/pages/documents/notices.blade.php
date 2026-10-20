@use('App\Domain\Organisation\Queries\PublicDirectory')
@php $locale = app()->getLocale(); @endphp
<x-layouts.public :title="__('documents.notices_title')" :description="__('documents.notices_description')">
    <div class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <div class="flex flex-wrap items-baseline justify-between gap-3">
            <h1 class="heading-1">{{ __('documents.notices_title') }}</h1>
            <a href="{{ route('documents.index') }}" class="text-sm font-medium text-link underline-offset-4 hover:underline">{{ __('documents.library_link') }}</a>
        </div>
        <p class="mt-2 max-w-prose text-ink-muted">{{ __('documents.notices_description') }}</p>

        <section aria-labelledby="notices-open" class="mt-8">
            <h2 id="notices-open" class="heading-2">{{ __('documents.open_notices') }}</h2>
            @if ($open->isEmpty())
                <p class="mt-4 rounded-card border border-line bg-surface px-5 py-6 text-ink-muted">{{ __('documents.none_open') }}</p>
            @else
                <ul class="mt-4 grid gap-4 lg:grid-cols-2">
                    @foreach ($open as $document)
                        @php $shown = PublicDirectory::shown($document, $locale); @endphp
                        @continue($shown === null)
                        <li><x-document-row :document="$document" :translation="$shown" :heading-level="3" /></li>
                    @endforeach
                </ul>
            @endif
        </section>

        @if ($closed->isNotEmpty())
            <section aria-labelledby="notices-closed" class="mt-12">
                <h2 id="notices-closed" class="heading-2">{{ __('documents.closed_notices') }}</h2>
                <ul class="mt-4 grid gap-4 lg:grid-cols-2">
                    @foreach ($closed as $document)
                        @php $shown = PublicDirectory::shown($document, $locale); @endphp
                        @continue($shown === null)
                        <li><x-document-row :document="$document" :translation="$shown" :heading-level="3" class="opacity-80" /></li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-layouts.public>
