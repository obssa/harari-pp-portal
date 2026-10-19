<x-filament-widgets::widget>
    <x-filament::section :heading="$this::title()" icon="heroicon-o-bell-alert">
        @php $items = $this->items(); @endphp
        @if ($items === [])
            <p style="font-size: .875rem; opacity: .7">{{ __('dashboard.attention.none') }}</p>
        @else
            <ul style="font-size: .875rem">
                @foreach ($items as $item)
                    <li style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: .5rem 0; border-top: 1px solid rgb(128 128 128 / .18)">
                        @if ($item['url'])
                            <a href="{{ $item['url'] }}" style="text-decoration: underline; text-underline-offset: 3px">{{ $item['label'] }}</a>
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
