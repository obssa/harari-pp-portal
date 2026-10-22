@if ($variant === 'drawer')
    {{-- ADR-0074: phones and tablets: one round button opens a sheet under the header with search, the sections
         (each opens in place), the call to join, the member portal and the party's pages. No script needed. --}}
    <details name="site-nav" class="group/menu">
        <summary class="inline-flex size-10 cursor-pointer list-none items-center justify-center rounded-full bg-ink-brand text-white transition-colors hover:bg-primary-900 dark:bg-white/10 dark:ring-1 dark:ring-white/25 dark:hover:bg-white/20 group-open/menu:bg-gold-500 group-open/menu:text-ink-brand group-open/menu:ring-0 [&::-webkit-details-marker]:hidden">
            <svg aria-hidden="true" class="size-5 group-open/menu:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M4 7h16M4 12h10M4 17h16"/></svg>
            <svg aria-hidden="true" class="hidden size-5 group-open/menu:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
            <span class="sr-only">{{ __('nav.menu') }}</span>
        </summary>
        <div class="absolute inset-x-0 top-full z-40 max-h-[calc(100dvh-4rem)] overflow-y-auto overscroll-contain border-t border-line bg-surface shadow-2xl">
            <div class="mx-auto max-w-page px-4 pt-4 pb-8 sm:px-6">
                @unless (request()->routeIs('search'))
                    <form method="get" action="{{ route('search') }}" role="search" class="flex gap-2">
                        <label for="drawer-search" class="sr-only">{{ __('search.label') }}</label>
                        <input id="drawer-search" type="search" name="q" maxlength="100" minlength="2" required placeholder="{{ __('search.short') }}"
                               class="min-w-0 flex-1 rounded-full border border-line bg-surface-muted px-4 py-2.5 text-base focus:border-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-600/30">
                        <button type="submit" class="inline-flex size-11 shrink-0 items-center justify-center rounded-full bg-ink-brand text-white hover:bg-primary-900">
                            <svg aria-hidden="true" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                            <span class="sr-only">{{ __('search.button') }}</span>
                        </button>
                    </form>
                @endunless

                <nav aria-label="{{ __('nav.main') }}" class="mt-4">
                    <ul class="divide-y divide-line overflow-hidden rounded-card border border-line">
                        @foreach ($items as $item)
                            <li>
                                @if ($item['children'] !== [])
                                    <details name="nav-section" class="group/section" @if ($item['active']) open @endif>
                                        <summary @class([
                                            'flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3.5 text-[1.05rem] font-bold [&::-webkit-details-marker]:hidden',
                                            'text-primary-700 dark:text-primary-300' => $item['active'],
                                        ])>
                                            {{ $item['label'] }}
                                            <svg aria-hidden="true" class="size-4 shrink-0 text-ink-muted transition-transform group-open/section:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="m6 9 6 6 6-6"/></svg>
                                        </summary>
                                        <ul class="grid gap-1 bg-surface-muted/60 px-2 py-2 sm:grid-cols-2">
                                            <li class="sm:col-span-2">
                                                <a href="{{ $item['url'] }}" class="flex items-center gap-1.5 rounded-control px-3 py-2.5 text-sm font-bold text-link hover:bg-surface">
                                                    {{ __('nav.section_all', ['section' => $item['label']]) }}
                                                    <svg aria-hidden="true" class="size-4 rtl:-scale-x-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                                                </a>
                                            </li>
                                            @foreach ($item['children'] as $child)
                                                <li>
                                                    <a href="{{ $child['url'] }}" @if ($child['external']) rel="noopener" @endif class="block rounded-control px-3 py-2.5 hover:bg-surface">
                                                        <span class="block font-semibold text-ink">{{ $child['label'] }}</span>
                                                        @if (filled($child['hint'] ?? null))
                                                            <span class="block text-xs text-ink-muted">{{ $child['hint'] }}</span>
                                                        @endif
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </details>
                                @else
                                    <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif @if ($item['external']) rel="noopener" @endif
                                       @class(['flex items-center justify-between gap-3 px-4 py-3.5 text-[1.05rem] font-bold', 'text-primary-700 dark:text-primary-300' => $item['active']])>
                                        {{ $item['label'] }}
                                        <svg aria-hidden="true" class="size-4 shrink-0 text-ink-muted rtl:-scale-x-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="m9 6 6 6-6 6"/></svg>
                                    </a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </nav>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <a href="{{ route('membership.apply') }}" class="inline-flex h-12 items-center justify-center gap-2 rounded-full bg-gold-500 font-bold text-ink-brand hover:bg-gold-400">
                        {{ __('nav.join') }}
                        <svg aria-hidden="true" class="size-4 rtl:-scale-x-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                    <a href="{{ route('member.sign-in') }}" class="inline-flex h-12 items-center justify-center gap-2 rounded-full border border-line font-bold text-ink hover:bg-surface-muted">
                        <svg aria-hidden="true" class="size-[1.1rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                        {{ __('nav.member_login') }}
                    </a>
                </div>

                @php $socialLinks = \App\Support\SocialLinks::all(); @endphp
                @if ($socialLinks !== [])
                    <ul class="mt-6 flex flex-wrap items-center justify-center gap-3">
                        @foreach ($socialLinks as $platform => $link)
                            <li>
                                <a href="{{ $link['url'] }}" rel="noopener me" class="inline-flex size-11 items-center justify-center rounded-full text-white shadow-sm transition-transform hover:-translate-y-0.5" style="background-color: {{ $link['color'] }}">
                                    <x-social-icon :platform="$platform" class="size-5" />
                                    <span class="sr-only">{{ $link['name'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
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
