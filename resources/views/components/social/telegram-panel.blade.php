@props(['url', 'preview' => null, 'posts', 'limit' => 4])
{{-- ADR-0072: the party's Telegram channel with its latest posts, read by the server and shown in the site's own
     cards (pictures copied to this site), so they appear even where Telegram itself is slow or blocked. --}}
<article {{ $attributes->class('flex flex-col overflow-hidden rounded-card border border-line bg-surface shadow-sm') }}>
    <header class="flex items-center gap-3 bg-[#16719F] px-4 py-3 text-white">
        <x-social-icon platform="telegram" class="size-8 shrink-0" />
        <div class="min-w-0 flex-1">
            <h3 class="leading-tight font-bold">{{ __('social.telegram.latest') }}</h3>
            <p class="truncate text-xs text-white/85">{{ \App\Support\SocialLinks::handle('telegram', $url) }}</p>
        </div>
        <a href="{{ $url }}" rel="noopener me" class="shrink-0 rounded-full bg-white px-4 py-1.5 text-sm font-bold text-[#16719F] hover:bg-white/90 focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-white">{{ __('social.action.join') }}</a>
    </header>

    @if ($posts->isNotEmpty())
        <ul class="flex-1 divide-y divide-line">
            @foreach ($posts->take($limit) as $post)
                @php $text = trim((string) $post->text); @endphp
                <li>
                    <a href="{{ $post->permalink }}" rel="noopener nofollow" class="group flex gap-4 p-4 transition-colors hover:bg-surface-muted focus-visible:outline-3 focus-visible:-outline-offset-3 focus-visible:outline-gold-500 sm:p-5">
                        @if ($post->image)
                            <span class="relative shrink-0 overflow-hidden rounded-lg">
                                <x-picture :media="$post->image" preset="square" sizes="7rem" class="size-20 object-cover transition-transform duration-500 group-hover:scale-105 sm:size-28" />
                                @if ($post->has_video)
                                    <span class="absolute inset-0 grid place-items-center bg-ink/30 text-white"><svg aria-hidden="true" class="size-8" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.5-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5z"/></svg></span>
                                @endif
                            </span>
                        @endif
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-2 text-xs text-ink-muted">
                                <x-date :date="$post->posted_at" />
                                @if ($post->is_pinned)<span>· {{ __('social.hub.pinned') }}</span>@endif
                            </span>
                            @if ($text !== '')
                                <span class="mt-1.5 line-clamp-3 block text-[0.95rem] leading-relaxed text-ink" dir="auto">{{ $text }}</span>
                            @endif
                            <span class="mt-2 inline-flex items-center gap-1 text-sm font-bold text-[#16719F] group-hover:underline">
                                {{ __('social.telegram.read') }}
                                <svg aria-hidden="true" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M9 7h8v8"/></svg>
                            </span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
        <footer class="border-t border-line px-4 py-3 text-sm sm:px-5">
            <a href="{{ $preview ?? $url }}" rel="noopener" class="inline-flex items-center gap-1 font-bold text-[#16719F] underline-offset-4 hover:underline">
                {{ __('social.telegram.all') }}
                <svg aria-hidden="true" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M9 7h8v8"/></svg>
            </a>
        </footer>
    @else
        <div class="relative isolate flex flex-1 flex-col items-start justify-center gap-4 overflow-hidden p-6 sm:p-8">
            <div aria-hidden="true" class="pointer-events-none absolute -end-16 -bottom-16 -z-10 size-64 opacity-15">
                @include('pages.home._plate', ['class' => 'size-full'])
            </div>
            <p class="max-w-prose text-lg text-ink">{{ __('social.telegram.text') }}</p>
            <p class="flex flex-wrap gap-3">
                <a href="{{ $url }}" rel="noopener me" class="rounded-full bg-[#16719F] px-5 py-2.5 font-bold text-white hover:bg-[#125d83]">{{ __('social.telegram.join') }}</a>
                @if ($preview)
                    <a href="{{ $preview }}" rel="noopener" class="rounded-full border border-line px-5 py-2.5 font-bold text-ink hover:bg-surface-muted">{{ __('social.telegram.preview') }}</a>
                @endif
            </p>
        </div>
    @endif
</article>
