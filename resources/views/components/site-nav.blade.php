@if ($variant === 'drawer')
    {{-- Phones and tablets: the whole menu behind one button, sections listed in full. --}}
    <details class="group/menu">
        <summary class="inline-flex cursor-pointer list-none items-center gap-2 rounded-full border border-line px-4 py-2.5 font-bold text-ink hover:bg-surface-muted [&::-webkit-details-marker]:hidden">
            <svg aria-hidden="true" class="size-5 group-open/menu:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            <svg aria-hidden="true" class="hidden size-5 group-open/menu:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
            {{ __('nav.menu') }}
        </summary>
        <nav aria-label="{{ __('nav.main') }}" class="absolute inset-x-0 top-full z-40 max-h-[80vh] overflow-y-auto border-t-3 border-gold-500 bg-surface shadow-2xl">
            <ul class="mx-auto max-w-page divide-y divide-line px-4 py-2 sm:px-6">
                @foreach ($items as $item)
                    <li class="py-3">
                        <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif @if ($item['external']) rel="noopener" @endif
                           @class(['block py-1 text-lg font-extrabold', 'text-primary-700 dark:text-primary-300' => $item['active']])>{{ $item['label'] }}</a>
                        @if ($item['children'] !== [])
                            <ul class="mt-2 grid gap-1 sm:grid-cols-2">
                                @foreach ($item['children'] as $child)
                                    <li>
                                        <a href="{{ $child['url'] }}" @if ($child['external']) rel="noopener" @endif
                                           class="block rounded-control px-3 py-2 font-semibold text-ink-muted hover:bg-surface-muted hover:text-ink">{{ $child['label'] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>
    </details>
@else
    {{-- ADR-0062: each section opens a full-width panel; name="site-nav" closes the others, no script needed. --}}
    <nav aria-label="{{ __('nav.main') }}">
        <ul class="-ms-3 flex flex-wrap gap-0.5 text-[15px] font-semibold">
            @foreach ($items as $item)
                <li>
                    @if ($item['children'] !== [])
                        <details name="site-nav" class="group">
                            <summary @class([
                                'inline-flex cursor-pointer list-none items-center gap-1.5 border-b-3 px-3 pt-3 pb-2.5 transition-colors duration-150 [&::-webkit-details-marker]:hidden',
                                'border-gold-500 text-primary-700 dark:text-primary-300' => $item['active'],
                                'border-transparent text-ink hover:text-primary-700 group-open:border-gold-500 group-open:bg-surface-muted group-open:text-primary-700' => ! $item['active'],
                            ])>
                                {{ $item['label'] }}
                                <svg aria-hidden="true" class="size-3 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="m6 9 6 6 6-6"/></svg>
                            </summary>
                            <div class="absolute inset-x-0 top-full z-40 border-t-3 border-gold-500 bg-surface shadow-2xl">
                                <div class="mx-auto grid max-w-page gap-8 px-4 py-8 sm:px-6 lg:grid-cols-[18rem_1fr]">
                                    <div class="flex flex-col gap-3">
                                        <p class="text-2xl font-extrabold tracking-tight">{{ $item['label'] }}</p>
                                        @if (filled($item['description'] ?? null))
                                            <p class="text-ink-muted">{{ $item['description'] }}</p>
                                        @endif
                                        <a href="{{ $item['url'] }}" class="inline-flex items-center gap-1.5 font-bold text-link">
                                            {{ __('nav.section_all', ['section' => $item['label']]) }}
                                            <svg aria-hidden="true" class="size-4 rtl:-scale-x-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                                        </a>
                                    </div>
                                    <ul class="grid content-start gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                        @foreach ($item['children'] as $child)
                                            <li>
                                                <a href="{{ $child['url'] }}" @if ($child['external']) rel="noopener" @endif
                                                   class="block h-full rounded-card bg-surface-muted px-4 py-3 text-ink hover:bg-primary-50 hover:text-primary-800 dark:hover:bg-primary-950 dark:hover:text-primary-200">
                                                    <span class="block font-bold">{{ $child['label'] }}</span>
                                                    @if (filled($child['hint'] ?? null))
                                                        <span class="mt-0.5 block text-sm text-ink-muted">{{ $child['hint'] }}</span>
                                                    @endif
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </details>
                    @else
                        <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif @if ($item['external']) rel="noopener" @endif
                           @class([
                               'inline-block border-b-3 px-3 pt-3 pb-2.5 transition-colors duration-150',
                               'border-gold-500 text-primary-700 dark:text-primary-300' => $item['active'],
                               'border-transparent text-ink hover:text-primary-700' => ! $item['active'],
                           ])>{{ $item['label'] }}</a>
                    @endif
                </li>
            @endforeach
        </ul>
    </nav>
@endif
