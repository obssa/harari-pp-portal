<x-mail::message>
# {{ __('membership.mail.code_heading') }}

{{ __('membership.mail.code_body', ['minutes' => $minutes]) }}

<x-mail::panel>
<span style="font-size: 28px; font-weight: 800; letter-spacing: 6px;">{{ $code }}</span>
</x-mail::panel>

{{ __('membership.mail.code_ignore') }}
</x-mail::message>
