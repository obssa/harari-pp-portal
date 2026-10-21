<x-mail::message>
{{ $body }}

---

<small>{{ __('campaigns.mail.why') }}
@if ($unsubscribe)
[{{ __('campaigns.mail.unsubscribe') }}]({{ $unsubscribe }})
@else
{{ __('campaigns.mail.test') }}
@endif
</small>
</x-mail::message>
