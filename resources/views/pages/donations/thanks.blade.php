@use('App\Domain\Donations\Actions\PledgeDonation')
<x-layouts.public :title="__('donations.thanks_title')">
    <div class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <div class="max-w-2xl">
            <h1 class="heading-1">{{ __('donations.thanks_title') }}</h1>
            <p class="mt-2 text-lg text-ink-muted">{{ __('donations.thanks_help', ['amount' => PledgeDonation::money($donation->amount, $donation->currency)]) }}</p>

            <div class="mt-8 rounded-card border border-primary-600/40 bg-primary-50 p-5 dark:bg-primary-950">
                <p class="text-sm text-ink-muted">{{ __('donations.reference') }}</p>
                <p class="mt-1 font-mono text-2xl font-semibold tracking-wider" dir="ltr">{{ $donation->reference }}</p>
                <p class="mt-2 text-sm">{{ __('donations.reference_help') }}</p>
            </div>

            @if ($accounts !== [])
                <h2 class="heading-3 mt-10">{{ __('donations.accounts') }}</h2>
                <ul class="mt-4 space-y-3">
                    @foreach ($accounts as $account)
                        <li class="rounded-card border border-line bg-surface p-4">
                            <p class="font-semibold">{{ $account['bank'] ?? '' }}</p>
                            <p class="text-ink-muted">{{ $account['name'] ?? '' }}</p>
                            <p class="mt-1 font-mono text-lg" dir="ltr">{{ $account['number'] ?? '' }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($instructions)
                <div class="mt-8 whitespace-pre-line">{{ $instructions }}</div>
            @endif

            @if ($donation->phone)
                <p class="mt-8 text-ink-muted">{{ __('donations.sms_sent') }}</p>
            @endif
        </div>
    </div>
</x-layouts.public>
