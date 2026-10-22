@props(['post', 'headingLevel' => 3])
@php
    $source = $post->source;
    $text = trim((string) $post->text);
    $brand = \App\Support\SocialLinks::PLATFORMS[$source->platform->value] ?? ['', '#475569', '#334155'];
@endphp
{{-- ADR-0072: a post gathered from one of the party's pages, in the site's card style with the platform's mark. --}}
<article {{ $attributes->class('group relative flex flex-col overflow-hidden rounded-card border border-line bg-surface shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md') }}>
    @if ($post->image)
        <div class="relative overflow-hidden">
            <x-picture :media="$post->image" preset="landscape" sizes="(min-width: 64rem) 24rem, (min-width: 40rem) 50vw, 100vw" class="aspect-video w-full object-cover transition-transform duration-500 group-hover:scale-105" />
            @if ($post->has_video)
                <span class="absolute inset-0 grid place-items-center bg-ink/25 text-white">
                    <span class="inline-flex size-14 items-center justify-center rounded-full bg-ink/70 backdrop-blur"><svg aria-hidden="true" class="size-7" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.5-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5z"/></svg></span>
                    <span class="sr-only">{{ __('social.hub.video') }}</span>
                </span>
            @endif
        </div>
    @endif
    <div class="flex flex-1 flex-col p-5">
        <p class="flex items-center gap-2.5 text-xs text-ink-muted">
            <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-full text-white" style="background-color: {{ $brand[1] }}"><x-social-icon :platform="$source->platform->value" class="size-4" /></span>
            <span class="min-w-0">
                <span class="block truncate font-semibold text-ink">{{ $source->name }}</span>
                <span class="block">{{ $source->platform->label() }} · <x-date :date="$post->posted_at" />@if ($post->is_pinned) · {{ __('social.hub.pinned') }}@endif</span>
            </span>
        </p>
        @if ($text !== '')
            <h{{ $headingLevel }} class="sr-only">{{ \Illuminate\Support\Str::limit($text, 80) }}</h{{ $headingLevel }}>
            <p class="mt-4 line-clamp-6 text-[0.95rem] leading-relaxed whitespace-pre-line text-ink" dir="auto">{{ \Illuminate\Support\Str::limit($text, 520) }}</p>
        @endif
        <p class="mt-auto pt-4 text-sm">
            <a href="{{ $post->permalink }}" rel="noopener nofollow" class="inline-flex items-center gap-1 font-bold underline-offset-4 after:absolute after:inset-0 hover:underline" style="color: {{ $brand[2] }}">
                {{ __('social.hub.open_on', ['platform' => $source->platform->label()]) }}
                <svg aria-hidden="true" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M9 7h8v8"/></svg>
            </a>
        </p>
    </div>
</article>
