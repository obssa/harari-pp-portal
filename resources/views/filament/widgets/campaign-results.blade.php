<x-filament-widgets::widget>
    <x-filament::section :heading="$this::title()" icon="heroicon-o-megaphone">
        @php $rows = $this->rows(); @endphp
        @if ($rows === [])
            <p style="font-size: .875rem; opacity: .7">{{ __('campaigns.dashboard.none') }}</p>
        @else
            <table style="width: 100%; font-size: .875rem">
                <thead>
                    <tr style="text-align: start; opacity: .7">
                        <th scope="col" style="text-align: start; font-weight: 500">{{ __('campaigns.field.name') }}</th>
                        <th scope="col" style="text-align: end; font-weight: 500">{{ __('campaigns.report.sent') }}</th>
                        <th scope="col" style="text-align: end; font-weight: 500">{{ __('campaigns.report.clicked') }}</th>
                        <th scope="col" style="text-align: end; font-weight: 500">{{ __('campaigns.report.unsubscribed') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td style="padding: .25rem 0">
                                <a href="{{ \App\Filament\Resources\Campaigns\CampaignResource::getUrl('view', ['record' => $row['campaign']]) }}" style="text-decoration: underline; text-underline-offset: 3px">{{ $row['campaign']->name }}</a>
                                <span style="opacity: .7">· {{ __('newsletter.channel.'.$row['campaign']->channel) }}</span>
                            </td>
                            <td style="text-align: end; font-variant-numeric: tabular-nums">{{ $row['sent'] }}</td>
                            <td style="text-align: end; font-variant-numeric: tabular-nums">{{ $row['campaign']->channel === 'email' ? $row['clicked'].'%' : '—' }}</td>
                            <td style="text-align: end; font-variant-numeric: tabular-nums">{{ $row['unsubscribed'] }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
