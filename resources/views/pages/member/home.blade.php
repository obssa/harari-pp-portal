<x-layouts.public :title="__('members.portal.title')">
    <div class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <h1 class="heading-1">{{ __('members.portal.welcome', ['name' => $member->name]) }}</h1>
        @include('pages.member.nav')

        @if (session('status'))
            <p role="status" class="mt-6 rounded-card border border-primary-600/40 bg-primary-50 px-4 py-3 text-primary-800 dark:bg-primary-950 dark:text-primary-200">{{ session('status') }}</p>
        @endif
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

        @if ($fayda)
            <section class="mt-10 max-w-3xl rounded-card border border-line bg-surface p-5" aria-labelledby="fayda-heading">
                <h2 id="fayda-heading" class="heading-3">{{ __('members.fayda.title') }}</h2>
                @if ($member->fayda_sub_hash)
                    <p class="mt-2 text-ink-muted">{{ __('members.fayda.is_linked') }}</p>
                    <form method="post" action="{{ route('member.fayda.unlink') }}" class="mt-4">
                        @csrf
                        <button type="submit" class="font-semibold text-link underline underline-offset-4">{{ __('members.fayda.unlink') }}</button>
                    </form>
                @else
                    <p class="mt-2 text-ink-muted">{{ __('members.fayda.link_help') }}</p>
                    <a href="{{ route('member.fayda.link') }}" class="mt-4 inline-flex items-center rounded-control bg-primary-600 px-5 py-2.5 font-semibold text-white hover:bg-primary-700">{{ __('members.fayda.link') }}</a>
                @endif
            </section>
        @endif
    </div>
</x-layouts.public>
