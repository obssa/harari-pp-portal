<x-filament-widgets::widget>
    <x-filament::section :heading="$this::title()" icon="heroicon-o-chart-bar">
        @php
            $traffic = $this->traffic();
            $peak = max(1, ...array_values($traffic['daily'] ?: [0]));
        @endphp
        <p style="display: flex; flex-wrap: wrap; align-items: baseline; gap: .5rem">
            <span style="font-size: 1.875rem; font-weight: 600; font-variant-numeric: tabular-nums">{{ \Illuminate\Support\Number::format($traffic['total']) }}</span>
            <span style="font-size: .875rem; opacity: .7">{{ __('dashboard.traffic.views') }}</span>
            @if ($traffic['change'] !== null)
                <x-filament::badge :color="$traffic['change'] >= 0 ? 'success' : 'warning'">
                    {{ __('dashboard.traffic.change', ['change' => ($traffic['change'] > 0 ? '+' : '').$traffic['change']]) }}
                </x-filament::badge>
            @endif
        </p>
        <div role="img" aria-label="{{ __('dashboard.traffic.daily_label', ['count' => count($traffic['daily'])]) }}"
             style="display: flex; align-items: flex-end; gap: 2px; height: 3rem; margin-top: .75rem">
            @foreach ($traffic['daily'] as $day => $views)
                <span title="{{ $day }}: {{ $views }}" style="flex: 1; min-width: 1px; border-radius: 2px 2px 0 0; background: rgb(49 81 237 / .75); height: {{ max(2, (int) round($views / $peak * 100)) }}%"></span>
            @endforeach
        </div>
        <div style="display: grid; gap: 1.5rem; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); margin-top: 1rem">
            @foreach (['languages' => __('dashboard.traffic.languages'), 'devices' => __('dashboard.traffic.devices')] as $list => $heading)
                <div>
                    <h3 style="font-size: .875rem; font-weight: 500; opacity: .7">{{ $heading }}</h3>
                    <ul style="margin-top: .5rem; font-size: .875rem">
                        @forelse ($traffic[$list] as $row)
                            <li style="display: flex; justify-content: space-between; gap: .75rem; padding: .125rem 0">
                                <span>{{ $row['label'] }}</span>
                                <span style="font-variant-numeric: tabular-nums; opacity: .7">{{ $row['share'] }}%</span>
                            </li>
                        @empty
                            <li style="opacity: .7">{{ __('dashboard.traffic.none') }}</li>
                        @endforelse
                    </ul>
                </div>
            @endforeach
        </div>
        <p style="margin-top: 1rem; font-size: .875rem">
            <a href="{{ \App\Filament\Pages\TrafficReports::getUrl() }}" style="text-decoration: underline; text-underline-offset: 3px">{{ __('dashboard.traffic.reports') }}</a>
        </p>
    </x-filament::section>
</x-filament-widgets::widget>
