<x-mail::message>
# {{ __('health.mail.heading') }}

{{ __('health.mail.body', ['name' => $name]) }}
</x-mail::message>
