@use('App\Domain\Donations\Actions\PledgeDonation')
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('donations.receipt.title') }} {{ $donation->receipt_no }} · {{ __('site.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-canvas text-ink antialiased">
    <main class="mx-auto max-w-2xl px-6 py-10">
        <div class="flex items-start justify-between gap-6 border-b border-line pb-6">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-primary-700">{{ __('site.name') }}</p>
                <p class="text-ink-muted">{{ __('site.office') }}</p>
            </div>
            <div class="text-end">
                <h1 class="heading-2">{{ __('donations.receipt.title') }}</h1>
                <p class="font-mono text-lg" dir="ltr">{{ $donation->receipt_no }}</p>
            </div>
        </div>

        <dl class="mt-8 grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm text-ink-muted">{{ __('donations.receipt.received_from') }}</dt>
                <dd class="mt-1 text-lg font-semibold">{{ $donation->displayName() }}</dd>
            </div>
            <div>
                <dt class="text-sm text-ink-muted">{{ __('donations.receipt.amount') }}</dt>
                <dd class="mt-1 text-lg font-semibold" dir="ltr">{{ PledgeDonation::money($donation->received_amount, $donation->currency) }}</dd>
            </div>
            <div>
                <dt class="text-sm text-ink-muted">{{ __('donations.receipt.received_on') }}</dt>
                <dd class="mt-1">@if ($donation->received_on)<x-date :date="$donation->received_on" />@endif</dd>
            </div>
            <div>
                <dt class="text-sm text-ink-muted">{{ __('donations.receipt.method') }}</dt>
                <dd class="mt-1">{{ __('donations.method.'.$donation->method) }}</dd>
            </div>
            <div>
                <dt class="text-sm text-ink-muted">{{ __('donations.reference') }}</dt>
                <dd class="mt-1 font-mono" dir="ltr">{{ $donation->reference }}</dd>
            </div>
            @if ($donation->payment_reference)
                <div>
                    <dt class="text-sm text-ink-muted">{{ __('donations.field.payment_reference') }}</dt>
                    <dd class="mt-1 font-mono" dir="ltr">{{ $donation->payment_reference }}</dd>
                </div>
            @endif
            @if ($donation->purpose)
                <div class="sm:col-span-2">
                    <dt class="text-sm text-ink-muted">{{ __('donations.field.purpose') }}</dt>
                    <dd class="mt-1">{{ $donation->purpose }}</dd>
                </div>
            @endif
        </dl>

        <p class="mt-10 text-lg">{{ __('donations.receipt.thanks') }}</p>
        <div class="mt-12 grid grid-cols-2 gap-10 text-sm text-ink-muted">
            <div class="border-t border-line pt-2">{{ __('donations.receipt.recorded_by') }}: {{ $donation->recorder?->name }}</div>
            <div class="border-t border-line pt-2">{{ __('donations.receipt.signature') }}</div>
        </div>

        <button type="button" id="print-receipt" class="mt-10 inline-flex items-center rounded-control bg-primary-600 px-5 py-2.5 font-semibold text-white hover:bg-primary-700 print:hidden">{{ __('members.portal.print') }}</button>
        <script nonce="{{ Vite::cspNonce() }}">document.getElementById('print-receipt').addEventListener('click', () => window.print());</script>
    </main>
</body>
</html>
