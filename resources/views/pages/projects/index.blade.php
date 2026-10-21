@use('App\Domain\Organisation\Queries\PublicDirectory')
@php
    $locale = app()->getLocale();
    $select = 'mt-1 block w-full rounded-control border border-line bg-surface px-3 py-2';
@endphp
<x-layouts.public :title="__('projects.title')" :description="__('projects.description')">
    <x-page-hero :title="__('projects.title')" :lead="__('projects.description')" tone="night" :crumbs="[]">
        <x-slot:actions><x-ui.button :href="route('projects.achievements')" variant="light" size="sm">{{ __('projects.achievements_link') }}</x-ui.button></x-slot:actions>
    </x-page-hero>
    <div class="mx-auto max-w-page px-4 pt-2 pb-12 sm:px-6">

        <form method="get" action="{{ route('projects.index') }}" role="search" aria-label="{{ __('projects.filter') }}" class="mt-6 grid gap-4 rounded-card border border-line bg-surface p-4 sm:grid-cols-4 sm:items-end">
            <div>
                <label for="filter-sector" class="text-sm font-medium">{{ __('projects.filter_sector') }}</label>
                <select id="filter-sector" name="sector" class="{{ $select }}">
                    <option value="">{{ __('projects.all_sectors') }}</option>
                    @foreach ($sectors as $id => $name)
                        <option value="{{ $id }}" @selected($filters['sector'] === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filter-status" class="text-sm font-medium">{{ __('projects.filter_status') }}</label>
                <select id="filter-status" name="status" class="{{ $select }}">
                    <option value="">{{ __('projects.all_statuses') }}</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
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
                    <a href="{{ route('projects.index') }}" class="self-center text-sm text-link underline-offset-4 hover:underline">{{ __('documents.clear') }}</a>
                @endif
            </div>
        </form>

        @if ($projects->isEmpty())
            <p class="mt-10 rounded-card border border-line bg-surface px-5 py-8 text-center text-ink-muted">{{ __('directory.empty') }}</p>
        @else
            <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($projects as $project)
                    @php $shown = PublicDirectory::shown($project, $locale); @endphp
                    @continue($shown === null)
                    <li class="flex"><x-project-card :project="$project" :translation="$shown" class="w-full" /></li>
                @endforeach
            </ul>

            <div class="mt-10">{{ $projects->links() }}</div>
        @endif
    </div>
</x-layouts.public>
