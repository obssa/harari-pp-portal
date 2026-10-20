<x-mail::message>
# {{ __('forms.mail.reply.heading') }}

@if (filled($name))
{{ __('events.registration.mail.greeting', ['name' => $name]) }}

@endif
{{ $text }}

**{{ __('forms.reference') }}: {{ $reference }}**

<x-mail::button :url="$url">
{{ __('forms.mail.received.button') }}
</x-mail::button>

{{ __('forms.mail.received.footer') }}
</x-mail::message>
