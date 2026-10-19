<x-filament-widgets::widget>
    <x-filament::section :heading="$this::title()" icon="heroicon-o-cpu-chip">
        @php $health = $this->health(); @endphp
        <p style="display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; font-size: .875rem">
            <x-filament::badge :color="$health['healthy'] ? 'success' : 'danger'">{{ $health['healthy'] ? __('dashboard.queues.healthy') : __('dashboard.queues.unhealthy') }}</x-filament::badge>
            {{ $health['heartbeat'] ? __('dashboard.queues.heartbeat', ['ago' => $health['heartbeat']->diffForHumans()]) : __('dashboard.queues.no_heartbeat') }}
        </p>
        <dl style="margin-top: .75rem; font-size: .875rem">
            @forelse ($health['queues'] as $queue)
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: .5rem 0; border-top: 1px solid rgb(128 128 128 / .18)">
                    <dt>{{ $queue['queue'] }}</dt>
                    <dd style="font-variant-numeric: tabular-nums">
                        {{ trans_choice('dashboard.queues.waiting', $queue['waiting'], ['count' => $queue['waiting']]) }}
                        @if ($queue['oldest'])
                            · {{ __('dashboard.queues.oldest', ['ago' => $queue['oldest']->diffForHumans()]) }}
                        @endif
                    </dd>
                </div>
            @empty
                <div style="padding: .5rem 0; opacity: .7">{{ __('dashboard.queues.empty') }}</div>
            @endforelse
            <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: .5rem 0; border-top: 1px solid rgb(128 128 128 / .18)">
                <dt>{{ __('dashboard.queues.failed') }}</dt>
                <dd style="font-variant-numeric: tabular-nums">{{ $health['failed'] }}</dd>
            </div>
        </dl>
    </x-filament::section>
</x-filament-widgets::widget>
