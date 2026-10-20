<x-filament-widgets::widget>
    <x-filament::section :heading="$this::title()" icon="heroicon-o-document-chart-bar">
        <ol style="font-size: .875rem">
            @forelse ($this->pages() as $page)
                <li style="display: flex; justify-content: space-between; gap: .75rem; padding: .25rem 0; border-top: 1px solid rgb(128 128 128 / .18)" lang="{{ $page['locale'] }}">
                    <a href="{{ url($page['label']) }}" target="_blank" rel="noopener" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap">{{ $page['label'] }}</a>
                    <span style="font-variant-numeric: tabular-nums; opacity: .7">{{ \Illuminate\Support\Number::format($page['views']) }}</span>
                </li>
            @empty
                <li style="opacity: .7">{{ __('dashboard.traffic.none') }}</li>
            @endforelse
        </ol>
    </x-filament::section>
</x-filament-widgets::widget>
