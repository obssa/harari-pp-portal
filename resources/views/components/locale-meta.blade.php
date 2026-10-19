<link rel="canonical" href="{{ $canonical }}">
@foreach ($urls as $locale => $url)
    <link rel="alternate" hreflang="{{ $locale }}" href="{{ $url }}">
@endforeach
@if ($default)
    <link rel="alternate" hreflang="x-default" href="{{ $default }}">
@endif
