<x-filament-widgets::widget>
    <x-filament::section :heading="$this::title()" icon="heroicon-o-document-chart-bar">
        <ol class="hpp-list">
            @forelse ($this->pages() as $page)
                <li class="hpp-row" lang="{{ $page['locale'] }}">
                    <a href="{{ url($page['label']) }}" target="_blank" rel="noopener" class="hpp-ellipsis">{{ $page['label'] }}</a>
                    <span class="hpp-num hpp-muted">{{ \Illuminate\Support\Number::format($page['views']) }}</span>
                </li>
            @empty
                <li class="hpp-muted">{{ __('dashboard.traffic.none') }}</li>
            @endforelse
        </ol>
    </x-filament::section>
</x-filament-widgets::widget>
