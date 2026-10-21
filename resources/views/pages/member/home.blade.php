<x-layouts.public :title="__('members.portal.title')">
    <div class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <h1 class="heading-1">{{ __('members.portal.welcome', ['name' => $member->name]) }}</h1>
        @include('pages.member.nav')

        @if ($member->status === \App\Domain\Membership\Enums\MemberStatus::Lapsed)
            <p role="alert" class="mt-6 rounded-card border border-warning-600/40 bg-warning-50 px-4 py-3 text-warning-800">{{ __('members.portal.lapsed') }}</p>
        @elseif ($dueSoon)
            <p role="status" class="mt-6 rounded-card border border-primary-600/40 bg-primary-50 px-4 py-3 text-primary-800 dark:bg-primary-950 dark:text-primary-200">
                {{ __('members.portal.due_soon') }} <x-date :date="$member->renewal_due_on" />
            </p>
        @endif

        <dl class="mt-8 grid max-w-3xl gap-4 sm:grid-cols-2">
            <div class="rounded-card border border-line bg-surface p-5">
                <dt class="text-sm text-ink-muted">{{ __('members.field.member_no') }}</dt>
                <dd class="mt-1 font-mono text-2xl font-semibold tracking-wider" dir="ltr">{{ $member->member_no }}</dd>
            </div>
            <div class="rounded-card border border-line bg-surface p-5">
                <dt class="text-sm text-ink-muted">{{ __('membership.admin.status') }}</dt>
                <dd class="mt-1 text-xl font-semibold">{{ $member->status->label() }}</dd>
            </div>
            <div class="rounded-card border border-line bg-surface p-5">
                <dt class="text-sm text-ink-muted">{{ __('members.field.joined_on') }}</dt>
                <dd class="mt-1"><x-date :date="$member->joined_on" /></dd>
            </div>
            <div class="rounded-card border border-line bg-surface p-5">
                <dt class="text-sm text-ink-muted">{{ __('members.field.renewal_due_on') }}</dt>
                <dd class="mt-1">@if ($member->renewal_due_on)<x-date :date="$member->renewal_due_on" />@else — @endif</dd>
            </div>
        </dl>
    </div>
</x-layouts.public>
