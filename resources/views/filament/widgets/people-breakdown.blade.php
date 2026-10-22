<x-filament-widgets::widget>
    <x-filament::section :heading="$this::title()" icon="heroicon-o-user-group">
        @php $figures = $this->figures(); @endphp
        <div style="display: grid; gap: 1.5rem; grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr))">
            @if ($figures['funnel'] !== null)
                <div>
                    <h3 class="hpp-subhead">{{ __('members.dashboard.funnel') }}</h3>
                    <ol class="hpp-list" style="margin-top: .5rem">
                        @foreach ($figures['funnel'] as $stage => $count)
                            <li style="display: flex; justify-content: space-between; gap: .75rem; padding: .125rem 0">
                                <span>{{ __("members.dashboard.stage.{$stage}") }}</span>
                                <span class="hpp-num">{{ $count }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif
            @foreach (['woreda', 'sex', 'age_band'] as $dimension)
                <div>
                    <h3 class="hpp-subhead">{{ \App\Domain\Membership\Support\PeopleStats::dimensionLabel($dimension) }}</h3>
                    <ol class="hpp-list" style="margin-top: .5rem">
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
                            <li class="hpp-muted">{{ __('members.dashboard.none') }}</li>
                        @endforelse
                    </ol>
                </div>
            @endforeach
        </div>
        <p class="hpp-list" style="margin-top: 1rem">
            <a href="{{ \App\Filament\Pages\MembershipReports::getUrl() }}" class="hpp-link">{{ __('members.dashboard.report') }}</a>
        </p>
    </x-filament::section>
</x-filament-widgets::widget>
