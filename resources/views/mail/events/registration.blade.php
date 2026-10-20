<x-mail::message>
# {{ __("events.registration.mail.{$kind}.heading") }}

{{ __('events.registration.mail.greeting', ['name' => $name]) }}

{{ __("events.registration.mail.{$kind}.body", ['event' => $title, 'position' => (string) $position]) }}

**{{ $title }}**<br>
{{ app(\App\Services\Calendar\EthiopianCalendar::class)->format($event->starts_at, null, withTime: ! $event->all_day) }}
@if (filled($translation?->venue))
<br>{{ $translation->venue }}
@endif

@if ($kind !== 'cancelled')
<x-mail::button :url="$ticketUrl">
{{ __('events.registration.mail.ticket_button') }}
</x-mail::button>
@endif

{{ __('events.registration.mail.footer') }}
</x-mail::message>
