@props(['url', 'preview' => null])
{{-- ADR-0073: the party's Telegram channel beside its Facebook page: the channel's name and what it carries,
     with Join and a way to read it in the browser. Its posts are in the wall below. --}}
<article {{ $attributes->class('relative isolate flex flex-col overflow-hidden rounded-card border border-line bg-surface shadow-sm') }}>
    <header class="flex items-center gap-3 bg-[#16719F] px-4 py-3 text-white">
        <x-social-icon platform="telegram" class="size-8 shrink-0" />
        <div class="min-w-0 flex-1">
            <h3 class="leading-tight font-bold">{{ \App\Support\SocialLinks::PLATFORMS['telegram'][0] }}</h3>
            <p class="truncate text-xs text-white/85">{{ \App\Support\SocialLinks::handle('telegram', $url) }}</p>
        </div>
        <a href="{{ $url }}" rel="noopener me" class="shrink-0 rounded-full bg-white px-4 py-1.5 text-sm font-bold text-[#16719F] hover:bg-white/90 focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-white">{{ __('social.action.join') }}</a>
    </header>
    <div class="relative isolate flex flex-1 flex-col justify-center gap-5 overflow-hidden p-6">
        <div aria-hidden="true" class="pointer-events-none absolute -end-14 -bottom-20 -z-10 size-56 opacity-15">
            @include('pages.home._plate', ['class' => 'size-full'])
        </div>
        <div class="flex items-start gap-4">
            <span class="inline-flex size-14 shrink-0 items-center justify-center rounded-2xl bg-[#229ED9] text-white shadow-sm"><x-social-icon platform="telegram" class="size-8" /></span>
            <div>
                <p class="text-lg font-bold text-ink">{{ __('site.name') }}</p>
                <p class="mt-1 text-ink-muted">{{ __('social.telegram.text') }}</p>
            </div>
        </div>
        <p class="flex flex-wrap gap-3">
            <a href="{{ $url }}" rel="noopener me" class="rounded-full bg-[#16719F] px-5 py-2.5 font-bold text-white hover:bg-[#125d83]">{{ __('social.telegram.join') }}</a>
            @if ($preview)
                <a href="{{ $preview }}" rel="noopener" class="rounded-full border border-line px-5 py-2.5 font-bold text-ink hover:bg-surface-muted">{{ __('social.telegram.preview') }}</a>
            @endif
        </p>
    </div>
</article>
