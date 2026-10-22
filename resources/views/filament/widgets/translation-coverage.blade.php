<x-filament-widgets::widget>
    <x-filament::section :heading="$this::title()" icon="heroicon-o-language">
        <table class="hpp-table">
            <thead>
                <tr>
                    <th scope="col">{{ __('dashboard.coverage.language') }}</th>
                    <th scope="col">{{ __('dashboard.coverage.interface') }}</th>
                    <th scope="col">{{ __('dashboard.coverage.posts') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->coverage() as $row)
                    <tr>
                        <th scope="row" lang="{{ $row['locale'] }}">{{ $row['name'] }}</th>
                        <td>{{ \Illuminate\Support\Number::percentage($row['interface'], 1) }}</td>
                        <td>{{ \Illuminate\Support\Number::percentage($row['posts'], 1) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-filament::section>
</x-filament-widgets::widget>
