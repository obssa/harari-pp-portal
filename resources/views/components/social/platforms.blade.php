@props(['links', 'stacked' => false])
{{-- ADR-0072: one card per page of the party, the platform's mark in its own colour, the page's name and what to do. --}}
@php $columns = [1 => 'sm:grid-cols-1', 2 => 'sm:grid-cols-2', 3 => 'sm:grid-cols-2 lg:grid-cols-3'][count($links)] ?? 'sm:grid-cols-2 lg:grid-cols-4'; @endphp
<ul {{ $attributes->class(['grid gap-3', $columns => ! $stacked]) }}>
    @foreach ($links as $platform => $link)
        <li>
            <a href="{{ $link['url'] }}" rel="noopener me"
               class="group flex h-full items-center gap-4 rounded-card border border-line bg-surface p-4 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-gold-500">
                <span class="inline-flex size-12 shrink-0 items-center justify-center rounded-full text-white shadow-sm" style="background-color: {{ $link['color'] }}">
                    <x-social-icon :platform="$platform" class="size-6" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block font-bold text-ink">{{ $link['name'] }}</span>
                    <span class="block truncate text-sm text-ink-muted">{{ $link['handle'] }}</span>
                </span>
                <span class="inline-flex shrink-0 items-center gap-1 rounded-full px-3 py-1.5 text-sm font-bold text-white transition-transform duration-200 group-hover:translate-x-0.5" style="background-color: {{ $link['dark'] }}">
                    {{ $link['action'] }}
                    <svg aria-hidden="true" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M9 7h8v8"/></svg>
                </span>
            </a>
        </li>
    @endforeach
</ul>
