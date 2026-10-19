<x-filament-widgets::widget>
    <x-filament::section :heading="$this::title()" icon="heroicon-o-queue-list">
        @php $pipeline = $this->pipeline(); @endphp
        <div style="overflow-x: auto">
            <table style="width: 100%; font-size: .875rem; border-collapse: collapse">
                <thead>
                    <tr>
                        <th scope="col" style="padding: .5rem .5rem .5rem 0; text-align: start; font-weight: 500; opacity: .7">{{ __('dashboard.pipeline.status') }}</th>
                        @foreach ($pipeline['languages'] as $language)
                            <th scope="col" style="padding: .5rem; text-align: end; font-weight: 500; opacity: .7">{{ strtoupper($language) }}</th>
                        @endforeach
                        <th scope="col" style="padding: .5rem; text-align: end; font-weight: 500; opacity: .7">{{ __('dashboard.pipeline.total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pipeline['rows'] as $row)
                        <tr style="border-top: 1px solid rgb(128 128 128 / .18)">
                            <th scope="row" style="padding: .5rem 0; text-align: start; font-weight: 400">{{ $row['status']->label() }}</th>
                            @foreach ($row['counts'] as $count)
                                <td style="padding: .5rem; text-align: end; font-variant-numeric: tabular-nums">{{ $count }}</td>
                            @endforeach
                            <td style="padding: .5rem; text-align: end; font-variant-numeric: tabular-nums; font-weight: 600">{{ $row['total'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
