@use('App\Domain\Organisation\Queries\PublicDirectory')
@php
    $locale = app()->getLocale();
    $select = 'mt-1 block w-full rounded-control border border-line bg-surface px-3 py-2';
@endphp
<x-layouts.public :title="__('documents.title')" :description="__('documents.description')">
    <x-page-hero :title="__('documents.title')" :lead="__('documents.description')" tone="plaster" :crumbs="[]">
        <x-slot:actions><x-ui.button :href="route('documents.notices')" variant="secondary" size="sm">{{ __('documents.notices_link') }}</x-ui.button></x-slot:actions>
    </x-page-hero>
    <div class="mx-auto max-w-page px-4 pt-2 pb-12 sm:px-6">

        <form method="get" action="{{ route('documents.index') }}" role="search" aria-label="{{ __('documents.filter') }}" class="mt-6 grid gap-4 rounded-card border border-line bg-surface p-4 sm:grid-cols-4 sm:items-end">
            <div>
                <label for="filter-type" class="text-sm font-medium">{{ __('documents.filter_type') }}</label>
                <select id="filter-type" name="type" class="{{ $select }}">
                    <option value="">{{ __('documents.all_types') }}</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filter-year" class="text-sm font-medium">{{ __('documents.filter_year') }}</label>
                <select id="filter-year" name="year" class="{{ $select }}">
                    <option value="">{{ __('documents.all_years') }}</option>
                    @foreach ($years as $year)
                        <option value="{{ $year }}" @selected($filters['year'] === (int) $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filter-office" class="text-sm font-medium">{{ __('documents.filter_office') }}</label>
                <select id="filter-office" name="office" class="{{ $select }}">
                    <option value="">{{ __('documents.all_offices') }}</option>
                    @foreach ($offices as $id => $name)
                        <option value="{{ $id }}" @selected($filters['office'] === $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="rounded-control bg-primary-600 px-4 py-2 font-semibold text-white hover:bg-primary-700">{{ __('documents.apply') }}</button>
                @if (array_filter($filters))
                    <a href="{{ route('documents.index') }}" class="self-center text-sm text-link underline-offset-4 hover:underline">{{ __('documents.clear') }}</a>
                @endif
            </div>
        </form>

        @if ($documents->isEmpty())
            <p class="mt-10 rounded-card border border-line bg-surface px-5 py-8 text-center text-ink-muted">{{ __('directory.empty') }}</p>
        @else
            <ul class="mt-8 grid gap-4 lg:grid-cols-2">
                @foreach ($documents as $document)
                    @php $shown = PublicDirectory::shown($document, $locale); @endphp
                    @continue($shown === null)
                    <li><x-document-row :document="$document" :translation="$shown" /></li>
                @endforeach
            </ul>

            <div class="mt-10">{{ $documents->links() }}</div>
        @endif
    </div>
</x-layouts.public>
