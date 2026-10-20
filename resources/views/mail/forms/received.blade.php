<x-mail::message>
# {{ __('forms.mail.received.heading') }}

@if (filled($name))
{{ __('events.registration.mail.greeting', ['name' => $name]) }}

@endif
@if (filled($body))
{{ $body }}
@else
{{ __('forms.mail.received.body', ['form' => $title]) }}
@endif

**{{ __('forms.reference') }}: {{ $reference }}**<br>
@if ($due)
{{ __('forms.mail.received.due', ['date' => app(\App\Services\Calendar\EthiopianCalendar::class)->format($due)]) }}
@endif

<x-mail::button :url="$url">
{{ __('forms.mail.received.button') }}
</x-mail::button>

{{ __('forms.mail.received.footer') }}
</x-mail::message>
