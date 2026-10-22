{{-- ADR-0071: the party's Facebook page beside its Telegram channel, the other pages and the latest posts gathered from them. --}}
<section aria-labelledby="home-social" class="bg-surface-muted">
    <div class="mx-auto max-w-page px-4 py-16 sm:px-6">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <div>
                <p class="kicker">{{ __('social.hub.title') }}</p>
                <h2 id="home-social" class="heading-2 mt-2">{{ $section->heading() }}</h2>
            </div>
            <a href="{{ route('social') }}" class="font-bold text-link underline-offset-4 hover:underline">{{ __('social.hub.all') }}</a>
        </div>

        <div @class(['mt-8 grid gap-8', 'lg:grid-cols-[minmax(0,500px)_1fr]' => $data['facebook'] !== null])>
            @if ($data['facebook'] !== null)
                <x-facebook-feed :page="$data['facebook']" :mode="$data['mode']" />
            @endif

            <div class="flex flex-col gap-6">
                @if ($data['telegram'] !== null)
                    <div class="relative isolate overflow-hidden rounded-card bg-primary-900 p-6 text-white sm:p-8">
                        <div aria-hidden="true" class="pointer-events-none absolute -end-16 -top-16 -z-10 size-56 opacity-25">
                            @include('pages.home._plate', ['class' => 'animate-spin-slower size-full'])
                        </div>
                        <p class="flex items-center gap-3">
                            <span class="inline-flex size-12 items-center justify-center rounded-full bg-[#229ed9] text-white"><x-social-icon platform="telegram" class="size-7" /></span>
                            <span class="heading-3 text-white">{{ __('social.telegram.title') }}</span>
                        </p>
                        <p class="mt-4 max-w-prose text-primary-100">{{ __('social.telegram.text') }}</p>
                        <p class="mt-6 flex flex-wrap gap-3">
                            <x-ui.button :href="$data['telegram']" variant="gold" rel="noopener me">{{ __('social.telegram.join') }}</x-ui.button>
                            <x-ui.button :href="$data['telegram_preview']" variant="light" rel="noopener">{{ __('social.telegram.preview') }}</x-ui.button>
                        </p>
                    </div>
                @endif

                @if ($data['links'] !== [])
                    <div>
                        <h3 class="text-sm font-bold tracking-wider text-ink-muted uppercase">{{ __('footer.follow') }}</h3>
                        <ul class="mt-3 flex flex-wrap gap-3">
                            @foreach ($data['links'] as $platform => $link)
                                <li>
                                    <a href="{{ $link['url'] }}" rel="noopener me" class="inline-flex items-center gap-2 rounded-full border border-line bg-surface px-4 py-2 font-semibold hover:border-primary-600 hover:text-primary-700">
                                        <x-social-icon :platform="$platform" class="size-5" />{{ $link['name'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($data['posts']->isNotEmpty())
                    <ul class="grid gap-5 sm:grid-cols-2">
                        @foreach ($data['posts'] as $post)
                            <li class="flex"><x-social-post :post="$post" class="w-full" /></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</section>
