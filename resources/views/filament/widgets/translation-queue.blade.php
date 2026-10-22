<x-filament-widgets::widget>
    <x-filament::section :heading="$this::title()" icon="heroicon-o-queue-list">
        <table class="hpp-table">
            <thead>
                <tr>
                    <th scope="col">{{ __('dashboard.coverage.language') }}</th>
                    <th scope="col">{{ __('translations.status.missing') }}</th>
                    <th scope="col">{{ __('translations.status.changed') }}</th>
                    <th scope="col">{{ __('translations.status.machine') }}</th>
                    <th scope="col">{{ __('dashboard.translation_queue.posts') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->queue() as $row)
                    <tr>
                        <th scope="row" lang="{{ $row['locale'] }}"><a href="{{ $row['url'] }}" class="hpp-link">{{ $row['name'] }}</a></th>
                        <td>{{ \Illuminate\Support\Number::format($row['missing']) }}</td>
                        <td>{{ \Illuminate\Support\Number::format($row['changed']) }}</td>
                        <td>{{ \Illuminate\Support\Number::format($row['machine']) }}</td>
                        <td>{{ \Illuminate\Support\Number::format($row['posts']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-filament::section>
</x-filament-widgets::widget>
