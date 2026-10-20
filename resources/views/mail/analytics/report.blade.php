<x-mail::message>
# {{ $name }}

{{ __('analytics.mail.intro', ['period' => $period, 'total' => \Illuminate\Support\Number::format($total)]) }}

<x-mail::table>
| {{ $dimension }} | {{ __('analytics.column.views') }} | {{ __('analytics.column.share') }} |
|:--|--:|--:|
@foreach ($rows as $row)
| {{ str_replace('|', '/', $row['label']) }} | {{ \Illuminate\Support\Number::format($row['views']) }} | {{ $row['share'] }}% |
@endforeach
</x-mail::table>

{{ __('analytics.mail.attachment') }}

{{ __('analytics.mail.why') }}
</x-mail::message>
