<x-layouts.public :description="__('site.description')">
    <h1 class="sr-only">{{ __('site.name') }}</h1>
    @php $heritageShown = false; @endphp
    @foreach ($sections as $item)
        @include('pages.home.'.$item['section']->key->value, ['section' => $item['section'], 'data' => $item['data']])
        {{-- ADR-0062: Harar's heritage band follows the events, or closes the page. --}}
        @if (! $heritageShown && in_array($item['section']->key->value, ['events', 'projects'], true))
            @include('pages.home._heritage')
            @php $heritageShown = true; @endphp
        @endif
    @endforeach
    @unless ($heritageShown)
        @include('pages.home._heritage')
    @endunless
</x-layouts.public>
