<x-filament-widgets::widget>
    <x-filament::section :heading="$this::title()" icon="heroicon-o-bolt">
        @php $actions = $this->actions(); @endphp
        @if ($actions === [])
            <p class="hpp-empty">{{ __('dashboard.quick.none') }}</p>
        @else
            {{-- ADR-0064: the most common tasks as tiles. --}}
            <div class="hpp-tiles">
                @foreach ($actions as $action)
                    <a href="{{ $action['url'] }}" class="hpp-tile">
                        <span class="hpp-tile-icon"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></span>
                        <span>{{ $action['label'] }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
