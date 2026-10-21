@use('App\Domain\Organisation\Enums\OfficeType')
@use('App\Domain\Organisation\Queries\PublicDirectory')
@php $locale = app()->getLocale(); @endphp
<x-layouts.public :title="$type ? $type->label() : __('offices.title')" :description="__('offices.description')">
    <x-page-hero :title="__('offices.title')" :lead="__('offices.description')" tone="plaster" :crumbs="[]" />
    <div class="mx-auto max-w-page px-4 pt-2 pb-12 sm:px-6">

        <form method="get" action="{{ route('offices.index') }}" role="search" class="mt-6 flex max-w-xl flex-wrap gap-2">
            @if ($type)
                <input type="hidden" name="type" value="{{ $type->value }}">
            @endif
            <label for="office-search" class="sr-only">{{ __('offices.search') }}</label>
            <input id="office-search" type="search" name="q" value="{{ $search }}" maxlength="80" placeholder="{{ __('offices.search') }}"
                   class="min-w-0 flex-1 rounded-control border border-line bg-surface px-3 py-2">
            <x-ui.button type="submit">{{ __('offices.search_button') }}</x-ui.button>
        </form>

        <nav aria-label="{{ __('offices.filter_by_type') }}" class="mt-6">
            <ul class="flex flex-wrap gap-2 text-sm">
                <li>
                    <a href="{{ route('offices.index', array_filter(['q' => $search])) }}" @if (! $type) aria-current="page" @endif
                       @class(['inline-block rounded-full border px-3 py-1', 'border-primary-600 bg-primary-50 text-primary-800 dark:bg-primary-950 dark:text-primary-200' => ! $type, 'border-line hover:bg-surface-muted' => (bool) $type])>{{ __('news.all') }}</a>
                </li>
                @foreach (OfficeType::cases() as $option)
                    <li>
                        <a href="{{ route('offices.index', array_filter(['type' => $option->value, 'q' => $search])) }}" @if ($type === $option) aria-current="page" @endif
                           @class(['inline-block rounded-full border px-3 py-1', 'border-primary-600 bg-primary-50 text-primary-800 dark:bg-primary-950 dark:text-primary-200' => $type === $option, 'border-line hover:bg-surface-muted' => $type !== $option])>{{ $option->label() }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>

        @if ($offices->isEmpty())
            <p class="mt-10 rounded-card border border-line bg-surface px-5 py-8 text-center text-ink-muted">{{ __('directory.empty') }}</p>
        @else
            <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($offices as $office)
                    @php $shown = PublicDirectory::shown($office, $locale); @endphp
                    @continue($shown === null)
                    <li lang="{{ $shown->locale }}">
                        <x-ui.card :title="$shown->getAttribute('name')" :href="route('offices.show', ['locale' => $shown->locale, 'slug' => $shown->slug])" :media="$office->coverImage" preset="photo" :heading-level="2">
                            {{ $shown->getAttribute('summary') }}
                            <x-slot:badges><x-ui.badge tone="primary">{{ $office->type->label() }}</x-ui.badge></x-slot:badges>
                            @if (filled($shown->getAttribute('address')))
                                <x-slot:meta>{{ $shown->getAttribute('address') }}</x-slot:meta>
                            @endif
                        </x-ui.card>
                    </li>
                @endforeach
            </ul>

            <div class="mt-10">{{ $offices->links() }}</div>
        @endif

        <p class="mt-12 text-sm">
            <a href="{{ route('structure') }}" class="text-link underline-offset-4 hover:underline">{{ __('structure.title') }}</a>
        </p>
    </div>
</x-layouts.public>
