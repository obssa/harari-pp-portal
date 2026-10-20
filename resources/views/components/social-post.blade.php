@props(['post', 'headingLevel' => 3])
@php
    $source = $post->source;
    $text = trim((string) $post->text);
@endphp
<article {{ $attributes->class('flex flex-col overflow-hidden rounded-card border border-line bg-surface') }}>
    @if ($post->image)
        <a href="{{ $post->permalink }}" rel="noopener nofollow" class="relative block" tabindex="-1" aria-hidden="true">
            <x-picture :media="$post->image" preset="landscape" sizes="(min-width: 64rem) 24rem, (min-width: 40rem) 50vw, 100vw" class="aspect-video w-full object-cover" />
            @if ($post->has_video)
                <span class="absolute start-2 top-2 rounded-control bg-ink/80 px-2 py-0.5 text-xs font-semibold text-white">{{ __('social.hub.video') }}</span>
            @endif
        </a>
    @endif
    <div class="flex flex-1 flex-col p-4">
        <p class="flex flex-wrap items-center gap-2 text-xs text-ink-muted">
            <x-ui.badge>{{ $source->platform->label() }}</x-ui.badge>
            <span class="font-medium text-ink">{{ $source->name }}</span>
            <span>· <x-date :date="$post->posted_at" /></span>
            @if ($post->is_pinned)
                <span>· {{ __('social.hub.pinned') }}</span>
            @endif
        </p>
        @if ($text !== '')
            <h{{ $headingLevel }} class="sr-only">{{ \Illuminate\Support\Str::limit($text, 80) }}</h{{ $headingLevel }}>
            <p class="mt-3 whitespace-pre-line text-sm" dir="auto">{{ \Illuminate\Support\Str::limit($text, 420) }}</p>
        @endif
        <p class="mt-auto pt-4 text-sm">
            <a href="{{ $post->permalink }}" rel="noopener nofollow" class="font-medium text-link underline-offset-4 hover:underline">{{ __('social.hub.open_on', ['platform' => $source->platform->label()]) }}</a>
        </p>
    </div>
</article>
