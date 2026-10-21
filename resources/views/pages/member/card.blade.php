@use('Illuminate\Support\Facades\Vite')
<x-layouts.public :title="__('members.portal.nav.card')">
    <div class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <h1 class="heading-1 print:hidden">{{ __('members.portal.nav.card') }}</h1>
        @include('pages.member.nav')

        <section aria-label="{{ __('members.portal.nav.card') }}"
                 class="mt-8 grid max-w-xl grid-cols-[1fr_auto] gap-6 rounded-card border-2 border-primary-600 bg-surface p-6 shadow-sm print:mt-0 print:shadow-none">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-primary-700">{{ __('members.portal.card_party') }}</p>
                <p class="mt-3 text-2xl font-semibold">{{ $member->name }}</p>
                <dl class="mt-4 space-y-2 text-sm">
                    <div><dt class="inline text-ink-muted">{{ __('members.field.member_no') }}:</dt> <dd class="inline font-mono font-semibold" dir="ltr">{{ $member->member_no }}</dd></div>
                    <div><dt class="inline text-ink-muted">{{ __('membership.field.woreda_id') }}:</dt> <dd class="inline">{{ $member->woreda?->name() ?? '—' }}</dd></div>
                    <div><dt class="inline text-ink-muted">{{ __('members.field.joined_on') }}:</dt> <dd class="inline"><x-date :date="$member->joined_on" /></dd></div>
                    <div><dt class="inline text-ink-muted">{{ __('members.portal.valid_until') }}:</dt> <dd class="inline">@if ($member->renewal_due_on)<x-date :date="$member->renewal_due_on" />@else — @endif</dd></div>
                </dl>
            </div>
            <div class="text-center">
                <img src="{{ $qr }}" alt="{{ __('members.portal.qr_alt') }}" width="132" height="132" class="size-33 bg-white">
                <p class="mt-1 max-w-33 text-xs text-ink-muted">{{ __('members.portal.qr_help') }}</p>
            </div>
        </section>

        <p class="mt-6 print:hidden">
            <button type="button" id="print-card" class="inline-flex items-center rounded-control bg-primary-600 px-5 py-2.5 font-semibold text-white hover:bg-primary-700">{{ __('members.portal.print') }}</button>
        </p>
        <script nonce="{{ Vite::cspNonce() }}">document.getElementById('print-card').addEventListener('click', () => window.print());</script>
    </div>
</x-layouts.public>
