<x-layouts.public :description="__('site.description')">
    <h1 class="sr-only">{{ __('site.name') }}</h1>
    @foreach ($sections as $item)
        @include('pages.home.'.$item['section']->key->value, ['section' => $item['section'], 'data' => $item['data']])
    @endforeach
</x-layouts.public>
