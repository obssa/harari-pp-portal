<x-layouts.public :title="__('members.portal.nav.events')">
    <div class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <h1 class="heading-1">{{ __('members.portal.nav.events') }}</h1>
        @include('pages.member.nav')
        @if ($registrations->isEmpty())
            <p class="mt-8 text-ink-muted">{{ __('members.portal.no_events') }}
                <a href="{{ route('events.index') }}" class="font-semibold text-link underline underline-offset-4">{{ __('volunteers.thanks.events') }}</a></p>
        @else
            <ul class="mt-8 max-w-3xl divide-y divide-line">
                @foreach ($registrations as $registration)
                    <li class="flex flex-wrap items-baseline justify-between gap-2 py-4">
                        <span>
                            <span class="block font-semibold">{{ $registration->event->title() }}</span>
                            <span class="text-sm text-ink-muted"><x-date :date="$registration->event->starts_at" /> · {{ $registration->status->label() }}</span>
                        </span>
                        <a href="{{ route('events.ticket', ['token' => $registration->token]) }}" class="text-sm font-semibold text-link underline underline-offset-4">{{ __('members.portal.ticket') }}</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.public>
