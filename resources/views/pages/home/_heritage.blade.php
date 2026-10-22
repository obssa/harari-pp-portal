@use('App\Domain\Content\Models\PageTranslation')
@php
    // The Harar page of the content pack (ADR-0063), linked when it is published in this language.
    $locale = app()->getLocale();
    $hararPage = PageTranslation::query()->with('page')->where('locale', $locale)->where('slug', 'harar')->first();
    $hararPath = $hararPage !== null && $hararPage->isLive() ? $hararPage->page?->path($locale) : null;
@endphp
{{-- ADR-0065: Harar itself — a Harari home hung with basket plates, a Jugol alley, the wall at Buda Gate. --}}
<section aria-labelledby="home-heritage" class="bg-surface-muted">
    <div class="mx-auto grid max-w-page items-center gap-12 px-4 py-20 sm:px-6 lg:grid-cols-[1.15fr_1fr]">
        <figure>
            <div class="grid h-[26rem] grid-cols-3 grid-rows-2 gap-3 sm:h-[34rem]">
                <div class="relative col-span-2 row-span-2 overflow-hidden rounded-[1.5rem] bg-primary-900">
                    <x-harar-photo name="interior" sizes="(min-width: 64rem) 42rem, 66vw" class="size-full object-cover" />
                    <span class="absolute start-4 bottom-4 max-w-[85%] rounded-full bg-white/90 px-3.5 py-1.5 text-xs font-bold text-ink-brand shadow-lg backdrop-blur">{{ __('harar.caption.interior') }}</span>
                </div>
                <div class="overflow-hidden rounded-[1.25rem] bg-primary-900">
                    <x-harar-photo name="alley" sizes="(min-width: 64rem) 21rem, 33vw" class="size-full object-cover" />
                </div>
                <div class="overflow-hidden rounded-[1.25rem] bg-primary-900">
                    <x-harar-photo name="wall" sizes="(min-width: 64rem) 21rem, 33vw" class="size-full object-cover" />
                </div>
            </div>
            <figcaption class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-ink-muted">
                <x-harar-credit name="interior" /><span aria-hidden="true">·</span>
                <x-harar-credit name="alley" /><span aria-hidden="true">·</span>
                <x-harar-credit name="wall" />
            </figcaption>
        </figure>
        <div class="flex flex-col gap-4">
            <p class="kicker">{{ __('homepage.heritage.kicker') }}</p>
            <h2 id="home-heritage" class="heading-2">{{ __('homepage.heritage.title') }}</h2>
            <p class="text-lg leading-relaxed text-ink-muted">{{ __('homepage.heritage.text') }}</p>
            <dl class="mt-2 grid grid-cols-3 gap-3 text-center">
                <div class="flex flex-col rounded-card bg-surface p-4 shadow-sm">
                    <dt class="text-xs font-semibold text-ink-muted">{{ __('homepage.heritage.fact_unesco') }}</dt>
                    <dd class="order-first text-2xl font-extrabold tracking-tight text-primary-800 dark:text-primary-200">2006</dd>
                </div>
                <div class="flex flex-col rounded-card bg-surface p-4 shadow-sm">
                    <dt class="text-xs font-semibold text-ink-muted">{{ __('homepage.heritage.fact_mosques') }}</dt>
                    <dd class="order-first text-2xl font-extrabold tracking-tight text-primary-800 dark:text-primary-200">82</dd>
                </div>
                <div class="flex flex-col rounded-card bg-surface p-4 shadow-sm">
                    <dt class="text-xs font-semibold text-ink-muted">{{ __('homepage.heritage.fact_gates') }}</dt>
                    <dd class="order-first text-2xl font-extrabold tracking-tight text-primary-800 dark:text-primary-200">6</dd>
                </div>
            </dl>
            <p class="text-sm leading-relaxed text-ink-muted">{{ __('homepage.heritage.pattern') }}</p>
            <p class="mt-2 flex flex-wrap gap-3">
                @if ($hararPath)
                    <x-ui.button :href="route('page.show', ['path' => $hararPath])">{{ __('homepage.heritage.discover') }}</x-ui.button>
                @endif
                <x-ui.button :href="route('offices.index')" :variant="$hararPath ? 'secondary' : 'primary'">{{ __('homepage.heritage.offices') }}</x-ui.button>
                <x-ui.button :href="route('leaders.index')" variant="secondary">{{ __('homepage.heritage.leaders') }}</x-ui.button>
            </p>
        </div>
    </div>
</section>
