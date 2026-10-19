@if ($deliveries->isEmpty())
    <p style="opacity: .75">{{ __('admin.webhooks.no_deliveries') }}</p>
@else
    <table style="width: 100%; font-size: .875rem; border-collapse: collapse">
        <thead>
            <tr style="text-align: start">
                <th style="padding: .375rem">{{ __('admin.webhooks.field.event') }}</th>
                <th style="padding: .375rem">{{ __('admin.webhooks.field.result') }}</th>
                <th style="padding: .375rem">{{ __('admin.webhooks.field.attempts') }}</th>
                <th style="padding: .375rem">{{ __('admin.revisions.field.saved_at') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($deliveries as $delivery)
                <tr style="border-top: 1px solid rgb(0 0 0 / .08)">
                    <td style="padding: .375rem">{{ $delivery->event }}</td>
                    <td style="padding: .375rem">
                        @if ($delivery->delivered_at)
                            {{ __('admin.webhooks.delivered', ['code' => $delivery->response_code]) }}
                        @else
                            {{ __('admin.webhooks.failed', ['code' => $delivery->response_code ?? '—']) }}
                            @if ($delivery->error)
                                <span style="display: block; opacity: .7">{{ \Illuminate\Support\Str::limit($delivery->error, 160) }}</span>
                            @endif
                        @endif
                    </td>
                    <td style="padding: .375rem">{{ $delivery->attempts }}</td>
                    <td style="padding: .375rem">{{ app(\App\Services\Calendar\EthiopianCalendar::class)->format($delivery->created_at, withTime: true) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
