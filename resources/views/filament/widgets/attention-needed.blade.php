<x-filament-widgets::widget>
    <x-filament::section :heading="$this::title()" icon="heroicon-o-bell-alert">
        @php $items = $this->items(); @endphp
        @if ($items === [])
            <p class="hpp-empty">{{ __('dashboard.attention.none') }}</p>
        @else
            <ul class="hpp-list">
                @foreach ($items as $item)
                    <li class="hpp-row">
                        @if ($item['url'])
                            <a href="{{ $item['url'] }}" class="hpp-link">{{ $item['label'] }}</a>
                        @else
                            <span>{{ $item['label'] }}</span>
                        @endif
                        <x-filament::badge :color="$item['tone']">{{ \Illuminate\Support\Number::format($item['count']) }}</x-filament::badge>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
