<nav aria-label="{{ __('nav.main') }}">
    <ul class="flex flex-wrap gap-1 text-sm font-medium">
        @foreach ($items as $item)
            <li class="relative">
                @if ($item['children'] !== [])
                    <details class="group">
                        <summary @class([
                            'inline-flex cursor-pointer list-none items-center gap-1 rounded-control px-3 py-2 transition-colors duration-150 [&::-webkit-details-marker]:hidden',
                            'bg-primary-50 text-primary-800 dark:bg-primary-950 dark:text-primary-200' => $item['active'],
                            'text-ink hover:bg-surface-muted' => ! $item['active'],
                        ])>
                            {{ $item['label'] }}
                            <span aria-hidden="true" class="text-xs transition-transform group-open:rotate-180">▾</span>
                        </summary>
                        <ul class="absolute start-0 z-20 mt-1 min-w-56 rounded-card border border-line bg-surface p-2 shadow-lg">
                            <li><a href="{{ $item['url'] }}" class="block rounded-control px-3 py-2 font-semibold hover:bg-surface-muted">{{ $item['label'] }}</a></li>
                            @foreach ($item['children'] as $child)
                                <li>
                                    <a href="{{ $child['url'] }}" @if ($child['external']) rel="noopener" @endif class="block rounded-control px-3 py-2 hover:bg-surface-muted">{{ $child['label'] }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @else
                    <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif @if ($item['external']) rel="noopener" @endif
                       @class([
                           'inline-block rounded-control px-3 py-2 transition-colors duration-150',
                           'bg-primary-50 text-primary-800 dark:bg-primary-950 dark:text-primary-200' => $item['active'],
                           'text-ink hover:bg-surface-muted' => ! $item['active'],
                       ])>{{ $item['label'] }}</a>
                @endif
            </li>
        @endforeach
    </ul>
</nav>
