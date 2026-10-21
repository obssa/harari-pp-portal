<x-mail::message>
# {{ $approved ? __('membership.mail.approved_heading') : __('membership.mail.rejected_heading') }}

{{ __('events.registration.mail.greeting', ['name' => $name]) }}

@if ($approved)
{{ __('membership.mail.approved_body') }}

**{{ __('membership.mail.member_no') }}: {{ $number }}**
@else
{{ __('membership.mail.rejected_body') }}

**{{ __('membership.admin.reason') }}:** {{ $reason }}
@endif

{{ __('membership.reference') }}: {{ $reference }}

<x-mail::button :url="$url">
{{ __('membership.status.link') }}
</x-mail::button>
</x-mail::message>
