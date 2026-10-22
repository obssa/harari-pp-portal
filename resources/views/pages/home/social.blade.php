{{-- ADR-0072: the party on social media: a card per page, the Facebook page beside the latest Telegram posts (read by
     the server, shown in the site's own cards), and posts gathered from the other pages. --}}
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

        @php
            // Pages with a panel of their own below are not repeated in the row of cards.
            $others = array_diff_key($data['links'], array_filter(['facebook' => $data['facebook'] !== null, 'telegram' => $data['telegram'] !== null]));
        @endphp
        @if ($others !== [])
            <x-social.platforms :links="$others" class="mt-8" />
        @endif

        @if ($data['facebook'] !== null || $data['telegram'] !== null)
            <div @class(['mt-8 grid items-start gap-6', 'lg:grid-cols-12' => $data['facebook'] !== null && $data['telegram'] !== null])>
                @if ($data['facebook'] !== null)
                    <x-facebook-feed :page="$data['facebook']['page']" :mode="$data['facebook']['mode']" :height="620" @class(['lg:col-span-5' => $data['telegram'] !== null]) />
                @endif
                @if ($data['telegram'] !== null)
                    <x-social.telegram-panel :url="$data['telegram']" :preview="$data['telegram_preview']" :posts="$data['telegram_posts']"
                                             @class(['lg:col-span-7' => $data['facebook'] !== null]) />
                @endif
            </div>
        @endif

        @if ($data['posts']->isNotEmpty())
            <ul class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($data['posts'] as $post)
                    <li class="flex"><x-social-post :post="$post" class="w-full" /></li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
