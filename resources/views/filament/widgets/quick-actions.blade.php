<x-filament-widgets::widget>
    <x-filament::section :heading="$this::title()" icon="heroicon-o-bolt">
        @php $actions = $this->actions(); @endphp
        @if ($actions === [])
            <p style="font-size: .875rem; opacity: .7">{{ __('dashboard.quick.none') }}</p>
        @else
            <div style="display: flex; flex-wrap: wrap; gap: .5rem">
                @foreach ($actions as $action)
                    <x-filament::button tag="a" :href="$action['url']" color="gray" size="sm">{{ $action['label'] }}</x-filament::button>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
