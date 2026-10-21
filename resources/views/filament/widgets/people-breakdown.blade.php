<x-filament-widgets::widget>
    <x-filament::section :heading="$this::title()" icon="heroicon-o-user-group">
        @php $figures = $this->figures(); @endphp
        <div style="display: grid; gap: 1.5rem; grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr))">
            @if ($figures['funnel'] !== null)
                <div>
                    <h3 style="font-size: .875rem; font-weight: 500; opacity: .7">{{ __('members.dashboard.funnel') }}</h3>
                    <ol style="margin-top: .5rem; font-size: .875rem">
                        @foreach ($figures['funnel'] as $stage => $count)
                            <li style="display: flex; justify-content: space-between; gap: .75rem; padding: .125rem 0">
                                <span>{{ __("members.dashboard.stage.{$stage}") }}</span>
                                <span style="font-variant-numeric: tabular-nums">{{ $count }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif
            @foreach (['woreda', 'sex', 'age_band'] as $dimension)
                <div>
                    <h3 style="font-size: .875rem; font-weight: 500; opacity: .7">{{ \App\Domain\Membership\Support\PeopleStats::dimensionLabel($dimension) }}</h3>
                    <ol style="margin-top: .5rem; font-size: .875rem">
                        @forelse ($figures[$dimension] as $row)
                            <li style="padding: .125rem 0">
                                <span style="display: flex; justify-content: space-between; gap: .75rem">
                                    <span>{{ $row['label'] }}</span>
                                    <span style="font-variant-numeric: tabular-nums; opacity: .8">{{ $row['count'] }} · {{ $row['share'] }}%</span>
                                </span>
                                <span aria-hidden="true" style="display: block; height: .25rem; margin-top: .125rem; border-radius: 999px; background: color-mix(in srgb, var(--primary-500, #3b82f6) 25%, transparent)">
                                    <span style="display: block; height: 100%; width: {{ min(100, $row['share']) }}%; border-radius: 999px; background: var(--primary-500, #3b82f6)"></span>
                                </span>
                            </li>
                        @empty
                            <li style="opacity: .7">{{ __('members.dashboard.none') }}</li>
                        @endforelse
                    </ol>
                </div>
            @endforeach
        </div>
        <p style="margin-top: 1rem; font-size: .875rem">
            <a href="{{ \App\Filament\Pages\MembershipReports::getUrl() }}" style="text-decoration: underline; text-underline-offset: 3px">{{ __('members.dashboard.report') }}</a>
        </p>
    </x-filament::section>
</x-filament-widgets::widget>
