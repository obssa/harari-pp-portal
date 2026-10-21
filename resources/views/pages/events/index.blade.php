@use('App\Domain\Organisation\Queries\PublicDirectory')
@php $locale = app()->getLocale(); @endphp
<x-layouts.public :title="$past ? __('events.past') : __('events.title')" :description="__('events.description')">
    <x-page-hero :title="__('events.title')" :lead="__('events.description')" tone="plaster" :crumbs="[]" />
    <div class="mx-auto max-w-page px-4 pt-2 pb-12 sm:px-6">

        <nav aria-label="{{ __('events.views') }}" class="mt-6">
            <ul class="flex flex-wrap gap-2 text-sm">
                <li><a href="{{ route('events.index') }}" @if (! $past) aria-current="page" @endif
                       @class(['inline-block rounded-full border px-3 py-1', 'border-primary-600 bg-primary-50 text-primary-800 dark:bg-primary-950 dark:text-primary-200' => ! $past, 'border-line hover:bg-surface-muted' => $past])>{{ __('events.upcoming') }}</a></li>
                <li><a href="{{ route('events.index', ['past' => 1]) }}" @if ($past) aria-current="page" @endif
                       @class(['inline-block rounded-full border px-3 py-1', 'border-primary-600 bg-primary-50 text-primary-800 dark:bg-primary-950 dark:text-primary-200' => $past, 'border-line hover:bg-surface-muted' => ! $past])>{{ __('events.past') }}</a></li>
                <li><a href="{{ route('events.index', ['month' => now(\App\Services\Calendar\EthiopianCalendar::timezone())->format('Y-m')]) }}" class="inline-block rounded-full border border-line px-3 py-1 hover:bg-surface-muted">{{ __('events.month_view') }}</a></li>
                <li><a href="{{ route('events.feed') }}" class="inline-block rounded-full border border-line px-3 py-1 hover:bg-surface-muted">{{ __('events.subscribe') }}</a></li>
            </ul>
        </nav>

        @if ($events->isEmpty())
            <p class="mt-10 rounded-card border border-line bg-surface px-5 py-8 text-center text-ink-muted">{{ $past ? __('directory.empty') : __('events.none_upcoming') }}</p>
        @else
            <ul class="mt-8 grid gap-4 lg:grid-cols-2">
                @foreach ($events as $event)
                    @php $shown = PublicDirectory::shown($event, $locale); @endphp
                    @continue($shown === null)
                    <li><x-event-row :event="$event" :translation="$shown" /></li>
                @endforeach
            </ul>

            <div class="mt-10">{{ $events->links() }}</div>
        @endif
    </div>
</x-layouts.public>
