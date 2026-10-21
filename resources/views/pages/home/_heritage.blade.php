{{-- ADR-0062: Harar's place in the design, a wall of woven basket plates as in a Harari house. --}}
@php $colours = ['#f5b800', '#29a8e0', '#ffffff', '#c2185b', '#14161a']; @endphp
<section aria-labelledby="home-heritage" class="mx-auto max-w-page px-4 py-16 sm:px-6">
    <div class="flex flex-wrap items-center gap-12">
        <div aria-hidden="true" class="grid min-w-0 flex-[999_1_28rem] grid-cols-5 gap-3 rounded-[1.5rem] bg-accent-600 p-6">
            @for ($i = 0; $i < 15; $i++)
                <svg viewBox="0 0 100 100" class="w-full opacity-80">
                    <circle cx="50" cy="50" r="46" fill="none" stroke="{{ $colours[$i % 5] }}" stroke-width="6" stroke-dasharray="8 4"/>
                    <circle cx="50" cy="50" r="32" fill="none" stroke="{{ $colours[($i + 2) % 5] }}" stroke-width="6" stroke-dasharray="3 3"/>
                    <circle cx="50" cy="50" r="16" fill="{{ $colours[($i + 4) % 5] }}"/>
                </svg>
            @endfor
        </div>
        <div class="flex flex-[1_1_22rem] flex-col gap-4">
            <p class="kicker">{{ __('homepage.heritage.kicker') }}</p>
            <h2 id="home-heritage" class="heading-2">{{ __('homepage.heritage.title') }}</h2>
            <p class="text-lg leading-relaxed text-ink-muted">{{ __('homepage.heritage.text') }}</p>
            <p class="text-sm leading-relaxed text-ink-muted">{{ __('homepage.heritage.pattern') }}</p>
            <p class="mt-2 flex flex-wrap gap-3">
                <x-ui.button :href="route('offices.index')">{{ __('homepage.heritage.offices') }}</x-ui.button>
                <x-ui.button :href="route('leaders.index')" variant="secondary">{{ __('homepage.heritage.leaders') }}</x-ui.button>
            </p>
        </div>
    </div>
</section>
