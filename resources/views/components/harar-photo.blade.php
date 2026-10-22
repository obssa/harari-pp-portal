@props(['name', 'sizes' => '100vw', 'loading' => 'lazy', 'fetchpriority' => null, 'alt' => null])
@use('App\Support\HararPhotos')
@php $photo = HararPhotos::get($name); @endphp
{{-- ADR-0065: a photo of Harar shipped with the site, credited where it is shown (see <x-harar-credit>). --}}
<picture>
    <source type="image/avif" srcset="{{ HararPhotos::srcset($name, 'avif') }}" sizes="{{ $sizes }}">
    <source type="image/webp" srcset="{{ HararPhotos::srcset($name, 'webp') }}" sizes="{{ $sizes }}">
    <img src="{{ HararPhotos::fallback($name) }}" alt="{{ $alt ?? __("harar.photo.{$name}") }}"
         width="{{ $photo['ratio'][0] }}" height="{{ $photo['ratio'][1] }}" loading="{{ $loading }}" decoding="async"
         @if ($fetchpriority) fetchpriority="{{ $fetchpriority }}" @endif
         {{ $attributes }}>
</picture>
