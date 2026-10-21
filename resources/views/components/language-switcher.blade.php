<nav aria-label="{{ __('nav.language') }}">
    <ul class="flex flex-wrap items-center gap-1">
        @foreach ($items as $item)
            <li><a href="{{ $item['url'] }}" lang="{{ $item['locale'] }}" hreflang="{{ $item['locale'] }}" @if ($item['current']) aria-current="true" @endif @class([
                'inline-block rounded-control px-2.5 py-1 transition-colors duration-150',
                'font-bold text-white underline decoration-gold-500 decoration-2 underline-offset-[6px]' => $item['current'],
                'text-white/85 hover:bg-white/10 hover:text-white' => ! $item['current'],
            ])>{{ $item['name'] }}</a></li>
        @endforeach
    </ul>
</nav>
