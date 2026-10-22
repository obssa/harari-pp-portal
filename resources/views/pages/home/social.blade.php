{{-- ADR-0072/0073: the party on social media: its Facebook page (Facebook's header: cover, followers, Follow) beside its
     Telegram channel, cards for its other pages, and a wall of the latest posts the server read from them, shown in
     the site's own cards so they appear wherever the site does. --}}
<section aria-labelledby="home-social" class="relative isolate overflow-hidden bg-surface-muted">
    <div aria-hidden="true" class="pointer-events-none absolute -start-32 -top-32 -z-10 size-96 opacity-[0.07]">
        @include('pages.home._plate', ['class' => 'animate-spin-slower size-full'])
    </div>
    <div class="mx-auto max-w-page px-4 py-16 sm:px-6 lg:py-20">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="max-w-2xl">
                <p class="kicker">{{ __('social.section.kicker') }}</p>
                <h2 id="home-social" class="heading-2 mt-2">{{ $section->heading() }}</h2>
                <p class="mt-3 text-lg text-ink-muted">{{ __('social.section.lead') }}</p>
            </div>
            <a href="{{ route('social') }}" class="inline-flex items-center gap-1 font-bold text-link underline-offset-4 hover:underline">
                {{ __('social.hub.all') }}
                <svg aria-hidden="true" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </a>
        </div>

        @if ($data['facebook'] !== null || $data['telegram'] !== null)
            <div @class(['mt-8 grid items-stretch gap-6', 'lg:grid-cols-2' => $data['facebook'] !== null && $data['telegram'] !== null])>
                @if ($data['facebook'] !== null)
                    <x-facebook-feed :page="$data['facebook']['page']" :mode="$data['facebook']['mode']" />
                @endif
                @if ($data['telegram'] !== null)
                    <x-social.channel-card :url="$data['telegram']" :preview="$data['telegram_preview']" />
                @endif
            </div>
        @endif

        @php
            // Pages with a card of their own above are not repeated in the row below.
            $others = array_diff_key($data['links'], array_filter(['facebook' => $data['facebook'] !== null, 'telegram' => $data['telegram'] !== null]));
        @endphp
        @if ($others !== [])
            <x-social.platforms :links="$others" class="mt-6" />
        @endif

        @if ($data['posts']->isNotEmpty())
            <h3 class="heading-3 mt-12">{{ __('social.wall.title') }}</h3>
            <ul class="mt-5 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                {{-- Whole rows of three on wide screens. --}}
                @foreach ($data['posts']->take(max(3, intdiv($data['posts']->count(), 3) * 3)) as $post)
                    <li class="flex"><x-social-post :post="$post" :heading-level="4" class="w-full" /></li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
