<x-filament-widgets::widget>
    <x-filament::section :heading="$this::title()" icon="heroicon-o-queue-list">
        @php $pipeline = $this->pipeline(); @endphp
        <div style="overflow-x: auto">
            <table class="hpp-table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('dashboard.pipeline.status') }}</th>
                        @foreach ($pipeline['languages'] as $language)
                            <th scope="col">{{ strtoupper($language) }}</th>
                        @endforeach
                        <th scope="col">{{ __('dashboard.pipeline.total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pipeline['rows'] as $row)
                        <tr>
                            <th scope="row">{{ $row['status']->label() }}</th>
                            @foreach ($row['counts'] as $count)
                                <td>{{ $count }}</td>
                            @endforeach
                            <td class="hpp-strong">{{ $row['total'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
