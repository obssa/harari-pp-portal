<x-mail::message>
# {{ __('forms.mail.notice.heading') }}

{{ __('forms.mail.notice.body', ['form' => $form, 'reference' => $reference]) }}

@if ($due)
{{ __('forms.mail.received.due', ['date' => app(\App\Services\Calendar\EthiopianCalendar::class)->format($due)]) }}
@endif

<x-mail::button :url="$url">
{{ __('forms.mail.notice.button') }}
</x-mail::button>
</x-mail::message>
