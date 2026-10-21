<x-layouts.public :title="__('members.portal.verify_title')">
    <div class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <div class="max-w-xl">
            <h1 class="heading-1">{{ __('members.portal.verify_title') }}</h1>
            @if ($member === null)
                <p role="alert" class="mt-6 rounded-card border border-danger-600/40 bg-danger-50 px-4 py-3 text-danger-800">{{ __('members.portal.verify_unknown') }}</p>
            @else
                <p role="status" @class(['mt-6 rounded-card border px-4 py-3 text-lg font-semibold',
                    'border-success-600/40 bg-success-50 text-success-800' => $valid,
                    'border-warning-600/40 bg-warning-50 text-warning-800' => ! $valid])>
                    {{ $valid ? __('members.portal.verify_valid') : __('members.portal.verify_not_valid') }}
                </p>
                <dl class="mt-6 grid gap-3">
                    <div><dt class="text-sm text-ink-muted">{{ __('members.field.member_no') }}</dt><dd class="font-mono font-semibold" dir="ltr">{{ $number }}</dd></div>
                    <div><dt class="text-sm text-ink-muted">{{ __('membership.field.name') }}</dt><dd>{{ $initials }}</dd></div>
                    <div><dt class="text-sm text-ink-muted">{{ __('membership.field.woreda_id') }}</dt><dd>{{ $member->woreda?->name() ?? '—' }}</dd></div>
                    <div><dt class="text-sm text-ink-muted">{{ __('members.portal.valid_until') }}</dt><dd>@if ($member->renewal_due_on)<x-date :date="$member->renewal_due_on" />@else — @endif</dd></div>
                </dl>
            @endif
        </div>
    </div>
</x-layouts.public>
