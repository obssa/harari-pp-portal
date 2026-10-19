<meta property="og:site_name" content="{{ __('site.name') }}">
<meta property="og:type" content="{{ $type }}">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:locale" content="{{ $ogLocale }}">
@foreach ($ogAlternates as $alternate)
    <meta property="og:locale:alternate" content="{{ $alternate }}">
@endforeach
@if ($image)
    <meta property="og:image" content="{{ $image }}">
    <meta name="twitter:card" content="summary_large_image">
@else
    <meta name="twitter:card" content="summary">
@endif
<meta name="twitter:title" content="{{ $title }}">
<meta name="twitter:description" content="{{ $description }}">
<script type="application/ld+json" nonce="{{ $nonce }}">{!! $json() !!}</script>
