{{--
    Component styleguide (PLAN.md §12). Developer-facing and never shown in
    production, so its labels are English (exempt from the hard-coded string
    check). ?lang=am|om|har shows the components with that language's strings
    and typography.
--}}
@use('App\Domain\Translation\Support\Locales')
@php
    $lang = Locales::isSupported(request()->query('lang')) ? (string) request()->query('lang') : 'en';
    app()->setLocale($lang);
    \Illuminate\Support\Facades\URL::defaults(['locale' => $lang]);

    $alternates = [];
    foreach (Locales::all() as $l) {
        $alternates[$l] = url('/styleguide?lang='.$l);
    }

    $scales = ['primary', 'accent'];
    $steps = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];
@endphp
<x-layouts.public title="Styleguide" :alternates="$alternates">
    <div class="mx-auto max-w-page space-y-14 px-4 py-12 sm:px-6">
        <header>
            <h1 class="heading-1">Styleguide</h1>
            <p class="mt-2 text-ink-muted">Design tokens and components of the public site (resources/css/tokens.css).</p>
        </header>

        <section aria-labelledby="sg-colours">
            <h2 id="sg-colours" class="heading-2">Colours</h2>
            @foreach ($scales as $scale)
                <h3 class="heading-3 mt-6 capitalize">{{ $scale }}</h3>
                <ul class="mt-3 grid grid-cols-4 gap-2 sm:grid-cols-6 lg:grid-cols-11">
                    @foreach ($steps as $step)
                        <li class="overflow-hidden rounded-control border border-line text-xs">
                            <span class="block h-12" style="background: var(--color-{{ $scale }}-{{ $step }})"></span>
                            <span class="block px-2 py-1">{{ $step }}</span>
                        </li>
                    @endforeach
                </ul>
            @endforeach
            <h3 class="heading-3 mt-6">Surfaces</h3>
            <ul class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-7">
                @foreach (['canvas', 'surface', 'surface-muted', 'line', 'ink', 'ink-muted', 'link'] as $token)
                    <li class="overflow-hidden rounded-control border border-line text-xs">
                        <span class="block h-12" style="background: var(--color-{{ $token }})"></span>
                        <span class="block px-2 py-1">{{ $token }}</span>
                    </li>
                @endforeach
            </ul>
        </section>

        <section aria-labelledby="sg-type">
            <h2 id="sg-type" class="heading-2">Typography</h2>
            <div class="mt-4 space-y-3">
                <p class="heading-1">{{ __('site.name') }}</p>
                <p class="heading-2">{{ __('holding.title') }}</p>
                <p class="heading-3">{{ __('site.office') }}</p>
                <p class="max-w-prose">{{ __('holding.body') }}</p>
                <p lang="am" class="max-w-prose">የሐረሪ ክልል ቅርንጫፍ ጽሕፈት ቤት — ሀ ለ ሐ መ ሠ ረ ሰ ሸ ቀ በ ተ ቸ ኀ ነ ኘ አ ከ ኸ ወ ዐ ዘ ዠ የ ደ ጀ ገ ጠ ጨ ጰ ጸ ፀ ፈ ፐ ። ፣ ፤ ፩ ፪ ፫</p>
                <p class="text-sm text-ink-muted">Ethiopic (lang="am"/"har") uses line-height 1.7 and 92% headline size.</p>
            </div>
        </section>

        <section aria-labelledby="sg-buttons">
            <h2 id="sg-buttons" class="heading-2">Buttons and badges</h2>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <x-ui.button>Primary</x-ui.button>
                <x-ui.button variant="accent">Accent</x-ui.button>
                <x-ui.button variant="secondary">Secondary</x-ui.button>
                <x-ui.button variant="ghost">Ghost</x-ui.button>
                <x-ui.button size="sm">Small</x-ui.button>
                <x-ui.button size="lg" href="#sg-buttons">Large link</x-ui.button>
                <x-ui.button disabled>Disabled</x-ui.button>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach (['primary', 'accent', 'neutral', 'success', 'warning', 'danger'] as $tone)
                    <x-ui.badge :tone="$tone">{{ ucfirst($tone) }}</x-ui.badge>
                @endforeach
            </div>
        </section>

        <section aria-labelledby="sg-cards">
            <h2 id="sg-cards" class="heading-2">Cards and dates</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-ui.card title="Sample news item (placeholder)" kicker="News" href="#sg-cards">
                    <x-slot:badges><x-ui.badge tone="primary">News</x-ui.badge><x-ui.badge>Harar</x-ui.badge></x-slot:badges>
                    Cards show a content-type badge and the office or league they belong to.
                    <x-slot:meta><x-date :date="now()" /></x-slot:meta>
                </x-ui.card>
                <x-ui.card title="Card without a link" kicker="Event">
                    Used where a card has no detail page.
                </x-ui.card>
                <div class="flex flex-wrap items-start gap-3">
                    <x-ui.date-tile :date="now()" />
                    <x-ui.date-tile :date="now()->setDate(2026, 9, 11)" />
                    <x-ui.date-tile :date="now()->setDate(2027, 9, 11)" />
                </div>
            </div>
        </section>
    </div>
</x-layouts.public>
