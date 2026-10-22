@props(['name'])
@use('App\Support\HararPhotos')
{{-- The attribution the photo's licence asks for, linked to its source. --}}
<a href="{{ HararPhotos::get($name)['source'] }}" rel="noopener license" {{ $attributes->class('underline-offset-2 hover:underline') }}>{{ __('content.photo_credit', ['credit' => HararPhotos::credit($name)]) }}</a>
