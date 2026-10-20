<x-filament-widgets::widget>
    <x-filament::section :heading="$this::title()" icon="heroicon-o-queue-list">
        <table style="width: 100%; font-size: .875rem; border-collapse: collapse">
            <thead>
                <tr>
                    <th scope="col" style="padding: .5rem .5rem .5rem 0; text-align: start; font-weight: 500; opacity: .7">{{ __('dashboard.coverage.language') }}</th>
                    <th scope="col" style="padding: .5rem; text-align: end; font-weight: 500; opacity: .7">{{ __('translations.status.missing') }}</th>
                    <th scope="col" style="padding: .5rem; text-align: end; font-weight: 500; opacity: .7">{{ __('translations.status.changed') }}</th>
                    <th scope="col" style="padding: .5rem; text-align: end; font-weight: 500; opacity: .7">{{ __('translations.status.machine') }}</th>
                    <th scope="col" style="padding: .5rem; text-align: end; font-weight: 500; opacity: .7">{{ __('dashboard.translation_queue.posts') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->queue() as $row)
                    <tr style="border-top: 1px solid rgb(128 128 128 / .18)">
                        <th scope="row" style="padding: .5rem 0; text-align: start; font-weight: 400" lang="{{ $row['locale'] }}"><a href="{{ $row['url'] }}" style="text-decoration: underline; text-underline-offset: 3px">{{ $row['name'] }}</a></th>
                        <td style="padding: .5rem; text-align: end; font-variant-numeric: tabular-nums">{{ \Illuminate\Support\Number::format($row['missing']) }}</td>
                        <td style="padding: .5rem; text-align: end; font-variant-numeric: tabular-nums">{{ \Illuminate\Support\Number::format($row['changed']) }}</td>
                        <td style="padding: .5rem; text-align: end; font-variant-numeric: tabular-nums">{{ \Illuminate\Support\Number::format($row['machine']) }}</td>
                        <td style="padding: .5rem; text-align: end; font-variant-numeric: tabular-nums">{{ \Illuminate\Support\Number::format($row['posts']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-filament::section>
</x-filament-widgets::widget>
