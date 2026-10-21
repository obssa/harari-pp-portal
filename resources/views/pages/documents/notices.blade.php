@use('App\Domain\Organisation\Queries\PublicDirectory')
@php $locale = app()->getLocale(); @endphp
<x-layouts.public :title="__('documents.notices_title')" :description="__('documents.notices_description')">
    <x-page-hero :title="__('documents.notices_title')" :lead="__('documents.notices_description')" tone="plaster" :crumbs="[[__('documents.title'), route('documents.index')]]">
        <x-slot:actions><x-ui.button :href="route('documents.index')" variant="secondary" size="sm">{{ __('documents.library_link') }}</x-ui.button></x-slot:actions>
    </x-page-hero>
    <div class="mx-auto max-w-page px-4 pt-2 pb-12 sm:px-6">

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
