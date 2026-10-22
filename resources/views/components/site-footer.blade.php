{{-- ADR-0062: ink ground under a band woven from the colours of Harari basket plates. --}}
<footer class="mt-16 bg-ink-brand text-sm text-white/80 [&_a]:text-white [&_a:hover]:text-gold-300">
    <svg aria-hidden="true" class="block h-[22px] w-full" preserveAspectRatio="none">
        <defs>
            <pattern id="footer-weave" width="56" height="22" patternUnits="userSpaceOnUse">
                <rect width="56" height="22" fill="#b4532a"/>
                <path d="M0 22 14 0l14 22Z" fill="#f5b800"/>
                <path d="M28 22 42 0l14 22Z" fill="#29a8e0"/>
                <path d="m10 22 4-7 4 7Z" fill="#14161a"/>
                <path d="m38 22 4-7 4 7Z" fill="#c2185b"/>
            </pattern>
        </defs>
        <rect width="100%" height="22" fill="url(#footer-weave)"/>
    </svg>
    <div class="mx-auto grid max-w-page gap-10 px-4 py-14 sm:px-6 md:grid-cols-2 lg:grid-cols-4">
        <section aria-labelledby="footer-about" class="lg:col-span-2">
            <div class="flex items-center gap-3">
                <span class="flex size-16 shrink-0 items-center justify-center rounded-full bg-white"><img src="/brand/logo-128.png" alt="" width="60" height="60" class="size-15 rounded-full"></span>
                <div>
                    <h2 id="footer-about" class="text-lg font-extrabold text-white">{{ __('site.name') }}</h2>
                    <p class="text-white/70">{{ __('site.office') }}</p>
                </div>
            </div>
            <p class="mt-4 max-w-prose text-white/70">{{ __('site.description') }}</p>
            {{-- ADR-0051: a plain GET form, so cached pages need no session; the newsletter page completes it. --}}
            <form method="get" action="{{ route('newsletter.create') }}" class="mt-6 max-w-md">
                <label for="footer-newsletter" class="text-xs font-bold tracking-wider text-gold-400 uppercase">{{ __('newsletter.footer_title') }}</label>
                <div class="mt-2 flex gap-2">
                    <input id="footer-newsletter" name="email" type="email" autocomplete="email" dir="ltr" placeholder="{{ __('newsletter.footer_placeholder') }}"
                           class="min-w-0 flex-1 rounded-control border border-white/20 bg-white/5 px-3 py-2.5 text-white placeholder:text-white/50 focus:border-gold-400 focus:outline-none focus:ring-2 focus:ring-gold-400/40">
                    <button type="submit" class="rounded-control bg-gold-500 px-4 py-2.5 font-bold text-ink-brand hover:bg-gold-400">{{ __('newsletter.footer_button') }}</button>
                </div>
                <p class="mt-2 text-xs text-white/60">{{ __('newsletter.footer_help') }}</p>
            </form>
        </section>

        @if ($links !== [])
            <section aria-labelledby="footer-links">
                <h2 id="footer-links" class="text-xs font-bold tracking-wider text-gold-400 uppercase">{{ __('footer.links') }}</h2>
                <ul class="mt-4 space-y-2">
                    @foreach ($links as $link)
                        <li><a href="{{ $link['url'] }}" @if ($link['external']) rel="noopener" @endif class="underline-offset-4 hover:underline">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section aria-labelledby="footer-contact">
            <h2 id="footer-contact" class="text-xs font-bold tracking-wider text-gold-400 uppercase">{{ __('footer.contact') }}</h2>
            @if ($hasContact())
                <dl class="mt-4 space-y-3 text-white/70">
                    @foreach ($contact as $label => $value)
                        <div>
                            <dt class="sr-only">{{ __($label) }}</dt>
                            <dd>{{ $value }}</dd>
                        </div>
                    @endforeach
                    @if ($phone)
                        <div>
                            <dt class="inline font-medium text-white">{{ __('footer.phone') }}:</dt>
                            <dd class="inline"><a class="underline-offset-4 hover:underline" href="tel:{{ $tel($phone) }}">{{ $phone }}</a></dd>
                        </div>
                    @endif
                    @if ($hotline)
                        <div>
                            <dt class="inline font-medium text-white">{{ __('footer.hotline') }}:</dt>
                            <dd class="inline"><a class="underline-offset-4 hover:underline" href="tel:{{ $tel($hotline) }}">{{ $hotline }}</a></dd>
                        </div>
                    @endif
                    @if ($email)
                        <div>
                            <dt class="inline font-medium text-white">{{ __('footer.email') }}:</dt>
                            <dd class="inline"><a class="break-all underline-offset-4 hover:underline" href="mailto:{{ $email }}">{{ $email }}</a></dd>
                        </div>
                    @endif
                </dl>
            @else
                <p class="mt-4 text-white/70">{{ __('footer.contact_soon') }}</p>
            @endif
        </section>

        @if ($social !== [])
            <section aria-labelledby="footer-follow">
                <h2 id="footer-follow" class="text-xs font-bold tracking-wider text-gold-400 uppercase">{{ __('footer.follow') }}</h2>
                {{-- ADR-0072: each page with its mark, coloured on hover. --}}
                <ul class="mt-4 space-y-2.5">
                    @foreach ($social as $platform => $link)
                        <li>
                            <a href="{{ $link['url'] }}" rel="noopener me" class="group inline-flex items-center gap-3 underline-offset-4 hover:underline">
                                <span class="inline-flex size-9 items-center justify-center rounded-full bg-white/10 text-white transition-colors group-hover:bg-[var(--brand)]" style="--brand: {{ \App\Support\SocialLinks::PLATFORMS[$platform][1] ?? '#1877F2' }}">
                                    <x-social-icon :platform="$platform" class="size-[1.1rem]" />
                                </span>
                                {{ $link['name'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>

    <div class="border-t border-white/10">
        <div class="mx-auto flex max-w-page flex-wrap items-center justify-between gap-2 px-4 py-4 text-xs text-white/60 sm:px-6">
            <p>{{ __('footer.copyright', ['years' => $years, 'name' => __('site.name')]) }}</p>
            @unless (app()->environment('production'))
                <p>{{ __('footer.test_site') }}</p>
            @endunless
        </div>
    </div>
</footer>
