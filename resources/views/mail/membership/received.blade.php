<x-mail::message>
# {{ __('membership.mail.received_heading') }}

{{ __('membership.mail.received_body', ['reference' => $reference]) }}

<x-mail::button :url="$url">
{{ __('membership.status.link') }}
</x-mail::button>
</x-mail::message>
