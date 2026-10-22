<x-filament-widgets::widget>
    <x-filament::section :heading="$this::title()" icon="heroicon-o-chart-bar">
        @php
            $traffic = $this->traffic();
            $peak = max(1, ...array_values($traffic['daily'] ?: [0]));
        @endphp
        <p style="display: flex; flex-wrap: wrap; align-items: baseline; gap: .5rem">
            <span class="hpp-big">{{ \Illuminate\Support\Number::format($traffic['total']) }}</span>
            <span class="hpp-empty">{{ __('dashboard.traffic.views') }}</span>
            @if ($traffic['change'] !== null)
                <x-filament::badge :color="$traffic['change'] >= 0 ? 'success' : 'warning'">
                    {{ __('dashboard.traffic.change', ['change' => ($traffic['change'] > 0 ? '+' : '').$traffic['change']]) }}
                </x-filament::badge>
            @endif
        </p>
        <div role="img" aria-label="{{ __('dashboard.traffic.daily_label', ['count' => count($traffic['daily'])]) }}"
             style="display: flex; align-items: flex-end; gap: 2px; height: 3rem; margin-top: .75rem">
            @foreach ($traffic['daily'] as $day => $views)
                <span title="{{ $day }}: {{ $views }}" style="flex: 1; min-width: 1px; border-radius: 2px 2px 0 0; background: linear-gradient(180deg, #0b6ca8, #083a5c); height: {{ max(2, (int) round($views / $peak * 100)) }}%"></span>
            @endforeach
        </div>
        <div style="display: grid; gap: 1.5rem; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); margin-top: 1rem">
            @foreach (['languages' => __('dashboard.traffic.languages'), 'devices' => __('dashboard.traffic.devices')] as $list => $heading)
                <div>
                    <h3 class="hpp-subhead">{{ $heading }}</h3>
                    <ul class="hpp-list" style="margin-top: .5rem">
                        @forelse ($traffic[$list] as $row)
                            <li style="display: flex; justify-content: space-between; gap: .75rem; padding: .125rem 0">
                                <span>{{ $row['label'] }}</span>
                                <span class="hpp-num hpp-muted">{{ $row['share'] }}%</span>
                            </li>
                        @empty
                            <li class="hpp-muted">{{ __('dashboard.traffic.none') }}</li>
                        @endforelse
                    </ul>
                </div>
            @endforeach
        </div>
        <p class="hpp-list" style="margin-top: 1rem">
            <a href="{{ \App\Filament\Pages\TrafficReports::getUrl() }}" class="hpp-link">{{ __('dashboard.traffic.reports') }}</a>
        </p>
    </x-filament::section>
</x-filament-widgets::widget>
