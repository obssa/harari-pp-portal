@use('App\Settings\SiteSettings')
@use('App\View\Components\SiteFooter')
@php
    $locale = app()->getLocale();
    [$kicker, $kickerLang] = $slide->text('kicker', $locale);
    [$title, $titleLang] = $slide->text('title', $locale);
    [$highlight] = $slide->text('highlight', $locale);
    [$text, $textLang] = $slide->text('text', $locale);
    [$note, $noteLang] = $slide->text('note', $locale);
    $titleWords = preg_split('/\s+/u', $title) ?: [];
    $lead = count($titleWords) > 1 ? array_shift($titleWords) : '';
    $rest = implode(' ', $titleWords);
    $noteWords = preg_split('/\s+/u', $note) ?: [];
    $first = $index === 0;
    try {
        $links = app(SiteSettings::class)->social_links;
    } catch (\Throwable) {
        $links = [];
    }
    $social = [];
    foreach (['facebook', 'telegram', 'youtube', 'x'] as $platform) {
        if (is_string($links[$platform] ?? null) && str_starts_with($links[$platform], 'https://')) {
            $social[$platform] = ['name' => SiteFooter::PLATFORMS[$platform], 'url' => $links[$platform]];
        }
    }
@endphp
{{-- ADR-0070: the party's campaign banner, rebuilt in HTML: the heading in outline, a slanted photo strip,
     the orange band and a row with the button, the site, the social pages and the slogan. --}}
<div class="relative isolate flex w-full overflow-hidden bg-white text-ink-brand {{ $height }}">
    <div aria-hidden="true" class="absolute inset-y-0 end-0 -z-10 hidden w-[24%] bg-linear-to-b from-[#ee8a3d] via-[#e5553a] to-[#d42f38] [clip-path:polygon(30%_0,100%_0,100%_100%,62%_100%)] lg:block"></div>
    <svg aria-hidden="true" class="absolute end-[2.5%] bottom-[14%] -z-10 hidden h-44 w-24 lg:block" viewBox="0 0 100 180" preserveAspectRatio="none" fill="none">
        <polygon points="1,1 42,1 99,179 58,179" stroke="#fff" stroke-opacity="0.85" stroke-width="2" vector-effect="non-scaling-stroke"/>
    </svg>
    <div aria-hidden="true" class="absolute top-0 start-[41%] -z-10 hidden h-36 w-24 bg-linear-to-b from-[#ee8a3d] to-[#d42f38] [clip-path:polygon(0_0,52%_0,100%_100%,48%_100%)] lg:block"></div>
    <div aria-hidden="true" class="absolute top-10 start-[38.5%] -z-10 hidden h-36 w-24 border-2 border-ink-brand/70 [transform:skewX(18deg)] lg:block"></div>

    <div class="mx-auto grid w-full max-w-page content-center items-center gap-x-10 gap-y-5 px-4 pt-6 pb-24 sm:px-6 sm:gap-y-6 lg:grid-cols-[1fr_1.3fr] lg:pt-10">
        <div class="animate-rise">
            <img src="/brand/logo-128.png" alt="" width="128" height="128" class="size-10 sm:size-16" @if (! $first) loading="lazy" @endif>
            @if ($kicker !== '')
                <p lang="{{ $kickerLang }}" class="mt-3 text-xs font-bold tracking-wider text-[#c92f22] uppercase">{{ $kicker }}</p>
            @endif
            <h2 lang="{{ $titleLang }}" class="mt-3 text-[clamp(2.4rem,5.6vw,4.6rem)] leading-[0.98] tracking-tight uppercase">
                <span class="sr-only">{{ trim($title.' '.$highlight) }}</span>
                <span aria-hidden="true">
                    @if ($lead !== '')
                        <span class="block text-ink-brand">@include('pages.home.slides._outline', ['word' => mb_strtoupper($lead), 'class' => 'block h-[0.8em] w-auto max-w-full overflow-visible'])</span>
                    @endif
                    <span class="mt-1 block font-light">{{ $rest }}@if ($highlight !== '') <span class="font-black text-[#c92f22] normal-case">{{ $highlight }}</span>@endif</span>
                </span>
            </h2>
            @if ($text !== '')
                <p lang="{{ $textLang }}" class="mt-4 max-w-xl text-[0.95rem] leading-relaxed font-medium text-ink sm:mt-5 sm:text-lg">{{ $text }}</p>
            @endif
        </div>

        @if ($slide->image)
            <div class="animate-rise relative [--rise-delay:160ms]">
                <x-picture :media="$slide->image" preset="landscape" sizes="(min-width: 64rem) 44rem, 100vw" :loading="$first ? 'eager' : 'lazy'" :fetchpriority="$first ? 'high' : null"
                           class="aspect-video w-full object-cover [clip-path:polygon(0_0,81%_0,100%_100%,19%_100%)]" />
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-x-5 gap-y-3 sm:mt-4 lg:col-span-2 lg:pe-44">
            @include('pages.home.slides._buttons', ['buttons' => $slide->buttonsFor($locale), 'tone' => 'banner', 'center' => false, 'margin' => 'mt-0'])
            <a href="{{ route('home') }}" class="hidden items-center gap-2 text-sm font-semibold tracking-wide text-ink-muted hover:text-ink sm:inline-flex">
                <svg aria-hidden="true" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg>
                {{ request()->getHost() }}
            </a>
            @if ($social !== [])
                <ul class="flex items-center gap-2">
                    @foreach ($social as $platform => $item)
                        <li>
                            <a href="{{ $item['url'] }}" rel="noopener" class="inline-flex size-10 items-center justify-center rounded-full bg-ink-brand text-white hover:bg-[#c92f22]">
                                <span class="sr-only">{{ $item['name'] }}</span>
                                <x-social-icon :platform="$platform" class="size-5" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
            @if ($note !== '')
                <p lang="{{ $noteLang }}" class="w-full text-[clamp(1.05rem,2.4vw,2rem)] font-semibold tracking-tight uppercase sm:w-auto lg:ms-auto">
                    <span class="sr-only">{{ $note }}</span>
                    <span aria-hidden="true" class="inline-flex flex-wrap items-baseline gap-x-3">
                        @foreach ($noteWords as $position => $word)
                            @if ($position === 0 && count($noteWords) > 1)
                                @include('pages.home.slides._outline', ['word' => mb_strtoupper($word), 'class' => 'inline-block h-[0.78em] w-auto overflow-visible text-ink-muted'])
                            @elseif ($position === count($noteWords) - 1 && count($noteWords) > 1)
                                <span class="font-bold text-[#c92f22]">{{ $word }}</span>
                            @else
                                <span class="text-ink-brand">{{ $word }}</span>
                            @endif
                        @endforeach
                    </span>
                </p>
            @endif
        </div>
    </div>
</div>
