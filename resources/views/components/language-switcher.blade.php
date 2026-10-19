<nav aria-label="{{ __('nav.language') }}">
    <ul class="flex flex-wrap items-center gap-1">
        @foreach ($items as $item)
            <li><a href="{{ $item['url'] }}" lang="{{ $item['locale'] }}" hreflang="{{ $item['locale'] }}" @if ($item['current']) aria-current="true" @endif @class([
                'inline-block rounded-control px-2.5 py-1 transition-colors duration-150',
                'bg-primary-700 text-white' => $item['current'],
                'text-ink hover:bg-line' => ! $item['current'],
            ])>{{ $item['name'] }}</a></li>
        @endforeach
    </ul>
</nav>
