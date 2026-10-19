<nav aria-label="{{ __('nav.language') }}">
    <ul class="flex flex-wrap items-center gap-1 text-sm">
        @foreach ($items as $item)
            <li><a href="{{ $item['url'] }}" lang="{{ $item['locale'] }}" hreflang="{{ $item['locale'] }}" @if ($item['current']) aria-current="true" @endif @class([
                'inline-block rounded-md px-2.5 py-1.5 transition-colors',
                'bg-blue-700 text-white' => $item['current'],
                'text-slate-700 hover:bg-slate-200' => ! $item['current'],
            ])>{{ $item['name'] }}</a></li>
        @endforeach
    </ul>
</nav>
