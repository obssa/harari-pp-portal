@use('App\Domain\Events\Enums\RegistrationStatus')
@use('App\Domain\Events\Support\TicketCode')
@php
    $status = $registration->status;
    $title = $translation?->title ?? $event->title();
    $tone = match ($status) { RegistrationStatus::Registered => 'success', RegistrationStatus::Waitlisted => 'warning', default => 'neutral' };
@endphp
<x-layouts.public :title="__('events.ticket.title')" :alternates="[]">
    <div class="mx-auto max-w-xl px-4 py-10 sm:px-6">
        @if (session('status'))
            <p role="status" class="mb-6 rounded-card border border-primary-600/40 bg-primary-50 px-4 py-3 text-primary-800 dark:bg-primary-950 dark:text-primary-200">{{ session('status') }}</p>
        @endif

        <article class="rounded-card border border-line bg-surface p-6 text-center">
            <h1 class="heading-2">{{ __('events.ticket.title') }}</h1>
            <p class="mt-4 text-lg font-semibold" @if ($translation) lang="{{ $translation->locale }}" @endif>{{ $title }}</p>
            <p class="mt-1 text-ink-muted"><x-date :date="$event->starts_at" :time="! $event->all_day" /></p>
            @if (filled($translation?->venue))
                <p class="mt-1 text-ink-muted">{{ $translation->venue }}</p>
            @endif

            <p class="mt-6"><x-ui.badge :tone="$tone">{{ $status->label() }}</x-ui.badge></p>
            <p class="mt-2 font-medium">{{ $registration->name }}</p>

            @if ($status === RegistrationStatus::Registered && ! $event->isPast())
                <img src="{{ TicketCode::image($token) }}" alt="{{ __('events.ticket.qr_alt') }}" width="240" height="240" class="mx-auto mt-6 size-60 rounded bg-white p-2">
                <p class="mt-3 text-sm text-ink-muted">{{ __('events.ticket.show_at_door') }}</p>
            @elseif ($status === RegistrationStatus::Waitlisted)
                <p class="mt-6">{{ __('events.ticket.waitlist_position', ['position' => $position]) }}</p>
            @elseif ($status === RegistrationStatus::Cancelled)
                <p class="mt-6 text-ink-muted">{{ __('events.ticket.cancelled') }}</p>
            @endif
        </article>

        <div class="mt-6 flex flex-wrap justify-center gap-2 text-sm">
            @if ($translation)
                <a href="{{ route('events.show', ['locale' => $translation->locale, 'slug' => $translation->slug]) }}" class="rounded-control border border-line px-3 py-1.5 hover:bg-surface-muted">{{ __('events.ticket.event_page') }}</a>
                @if ($status === RegistrationStatus::Registered && ! $event->isPast())
                    <a href="{{ route('events.ics', ['locale' => $translation->locale, 'slug' => $translation->slug]) }}" class="rounded-control border border-line px-3 py-1.5 hover:bg-surface-muted">{{ __('events.add_to_calendar') }}</a>
                @endif
            @endif
        </div>

        @if ($status !== RegistrationStatus::Cancelled && ! $event->isPast())
            <details class="mt-8 rounded-card border border-line p-4">
                <summary class="cursor-pointer font-medium">{{ __('events.ticket.cancel_title') }}</summary>
                <p class="mt-3 text-sm text-ink-muted">{{ __('events.ticket.cancel_help') }}</p>
                <form method="post" action="{{ route('events.ticket.cancel', ['token' => $token]) }}" class="mt-4">
                    @csrf
                    <button type="submit" class="rounded-control border border-danger-600 px-4 py-2 font-medium text-danger-600 hover:bg-danger-50">{{ __('events.ticket.cancel') }}</button>
                </form>
            </details>
        @endif

        <p class="mt-8 text-center text-sm text-ink-muted">{{ __('events.ticket.keep_link') }}</p>
    </div>
</x-layouts.public>
