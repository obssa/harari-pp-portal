<x-filament-widgets::widget>
    <x-filament::section :heading="$this::title()" icon="heroicon-o-language">
        <table style="width: 100%; font-size: .875rem; border-collapse: collapse">
            <thead>
                <tr>
                    <th scope="col" style="padding: .5rem .5rem .5rem 0; text-align: start; font-weight: 500; opacity: .7">{{ __('dashboard.coverage.language') }}</th>
                    <th scope="col" style="padding: .5rem; text-align: end; font-weight: 500; opacity: .7">{{ __('dashboard.coverage.interface') }}</th>
                    <th scope="col" style="padding: .5rem; text-align: end; font-weight: 500; opacity: .7">{{ __('dashboard.coverage.posts') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->coverage() as $row)
                    <tr style="border-top: 1px solid rgb(128 128 128 / .18)">
                        <th scope="row" style="padding: .5rem 0; text-align: start; font-weight: 400" lang="{{ $row['locale'] }}">{{ $row['name'] }}</th>
                        <td style="padding: .5rem; text-align: end; font-variant-numeric: tabular-nums">{{ \Illuminate\Support\Number::percentage($row['interface'], 1) }}</td>
                        <td style="padding: .5rem; text-align: end; font-variant-numeric: tabular-nums">{{ \Illuminate\Support\Number::percentage($row['posts'], 1) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-filament::section>
</x-filament-widgets::widget>
