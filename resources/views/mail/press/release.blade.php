<x-mail::message>
{{ __('events.registration.mail.greeting', ['name' => $name]) }}

@if (filled($kicker))
**{{ $kicker }}**
@endif

# {{ $title }}

@if (filled($excerpt))
{{ $excerpt }}
@endif

<x-mail::button :url="$url">
{{ __('press.mail.read') }}
</x-mail::button>

<small>{{ __('press.mail.why') }} [{{ __('press.mail.opt_out') }}]({{ $optOut }})</small>
</x-mail::message>
