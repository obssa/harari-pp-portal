@props(['title', 'lead' => null, 'kicker' => null, 'tone' => 'plaster', 'crumbs' => [], 'headingLang' => null])
@php
    $dark = in_array($tone, ['night', 'ink', 'magenta', 'primary', 'accent'], true);
    $ground = match ($tone) {
        'night' => 'bg-primary-900 text-white',
        'ink' => 'bg-ink-brand text-white',
        'magenta' => 'bg-magenta-700 text-white',
        'primary' => 'bg-primary-700 text-white',
        'accent' => 'bg-accent-700 text-white',
        'gold' => 'bg-gold-500 text-ink-brand',
        default => 'bg-surface-muted',
    };
@endphp
{{-- ADR-0062: every page opens on a band with its breadcrumb, title and lead; dark bands carry a basket plate. --}}
<section {{ $attributes->class(['relative overflow-hidden', $ground]) }}>
    @if ($dark)
        @include('pages.home._plate', ['class' => 'pointer-events-none absolute -end-24 -bottom-40 size-80 opacity-40 sm:size-[26rem]'])
    @endif
    <div class="relative mx-auto flex max-w-page flex-col gap-4 px-4 py-8 sm:px-6 sm:pt-10 sm:pb-10">
        <nav aria-label="{{ __('news.breadcrumb') }}">
            <ol @class([
                'flex flex-wrap items-center gap-x-2 gap-y-1 text-sm',
                'text-white/75 [&_a:hover]:text-white' => $dark,
                'text-ink-brand/75' => $tone === 'gold',
                'text-ink-muted [&_a:hover]:text-ink' => ! $dark && $tone !== 'gold',
            ])>
                <li><a href="{{ route('home') }}" class="underline-offset-4 hover:underline">{{ __('nav.home') }}</a></li>
                @foreach ($crumbs as $crumb)
                    <li class="flex items-center gap-2">
                        <span aria-hidden="true">/</span>
                        <a href="{{ $crumb[1] }}" class="underline-offset-4 hover:underline">{{ $crumb[0] }}</a>
                    </li>
                @endforeach
                <li class="flex min-w-0 items-center gap-2">
                    <span aria-hidden="true">/</span>
                    <span aria-current="page" @class(['line-clamp-1 font-semibold', 'text-white' => $dark, 'text-ink' => ! $dark])>{{ $title }}</span>
                </li>
            </ol>
        </nav>
        <div class="flex flex-wrap items-end justify-between gap-x-8 gap-y-5">
            <div class="max-w-3xl">
                @if ($kicker)
                    <p @class(['kicker', 'text-gold-400!' => $dark, 'text-ink-brand!' => $tone === 'gold'])>{{ $kicker }}</p>
                @endif
                <h1 @if ($headingLang) lang="{{ $headingLang }}" @endif @class(['heading-1', 'mt-2' => $kicker, 'text-white' => $dark])>{{ $title }}</h1>
                @if ($lead)
                    <p @class(['mt-3 max-w-prose text-lg', 'text-white/85' => $dark, 'text-ink-brand/80' => $tone === 'gold', 'text-ink-muted' => ! $dark && $tone !== 'gold'])>{{ $lead }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex flex-wrap gap-2">{{ $actions }}</div>
            @endisset
        </div>
        @if (trim((string) $slot) !== '')
            <div>{{ $slot }}</div>
        @endif
    </div>
</section>
