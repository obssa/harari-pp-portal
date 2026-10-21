<x-mail::message>
# {{ $alreadyConfirmed ? __('newsletter.mail.preferences_heading') : __('newsletter.mail.confirm_heading') }}

{{ $alreadyConfirmed ? __('newsletter.mail.preferences_body') : __('newsletter.mail.confirm_body') }}

<x-mail::button :url="$url">
{{ $alreadyConfirmed ? __('newsletter.mail.preferences_button') : __('newsletter.mail.confirm_button') }}
</x-mail::button>

{{ __('newsletter.mail.ignore') }}
</x-mail::message>
