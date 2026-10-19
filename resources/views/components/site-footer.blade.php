<footer class="mt-16 border-t border-line bg-surface text-sm">
    <div class="mx-auto grid max-w-page gap-10 px-4 py-12 sm:px-6 md:grid-cols-2 lg:grid-cols-4">
        <section aria-labelledby="footer-about" class="lg:col-span-2">
            <h2 id="footer-about" class="text-base font-semibold">{{ __('site.name') }}</h2>
            <p class="mt-1 text-ink-muted">{{ __('site.office') }}</p>
            <p class="mt-4 max-w-prose text-ink-muted">{{ __('site.description') }}</p>
        </section>

        @if ($links !== [])
            <section aria-labelledby="footer-links">
                <h2 id="footer-links" class="text-base font-semibold">{{ __('footer.links') }}</h2>
                <ul class="mt-4 space-y-2">
                    @foreach ($links as $link)
                        <li><a href="{{ $link['url'] }}" @if ($link['external']) rel="noopener" @endif class="text-link underline-offset-4 hover:underline">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section aria-labelledby="footer-contact">
            <h2 id="footer-contact" class="text-base font-semibold">{{ __('footer.contact') }}</h2>
            @if ($hasContact())
                <dl class="mt-4 space-y-3 text-ink-muted">
                    @foreach ($contact as $label => $value)
                        <div>
                            <dt class="sr-only">{{ __($label) }}</dt>
                            <dd>{{ $value }}</dd>
                        </div>
                    @endforeach
                    @if ($phone)
                        <div>
                            <dt class="inline font-medium text-ink">{{ __('footer.phone') }}:</dt>
                            <dd class="inline"><a class="underline-offset-4 hover:underline" href="tel:{{ $tel($phone) }}">{{ $phone }}</a></dd>
                        </div>
                    @endif
                    @if ($hotline)
                        <div>
                            <dt class="inline font-medium text-ink">{{ __('footer.hotline') }}:</dt>
                            <dd class="inline"><a class="underline-offset-4 hover:underline" href="tel:{{ $tel($hotline) }}">{{ $hotline }}</a></dd>
                        </div>
                    @endif
                    @if ($email)
                        <div>
                            <dt class="inline font-medium text-ink">{{ __('footer.email') }}:</dt>
                            <dd class="inline"><a class="break-all underline-offset-4 hover:underline" href="mailto:{{ $email }}">{{ $email }}</a></dd>
                        </div>
                    @endif
                </dl>
            @else
                <p class="mt-4 text-ink-muted">{{ __('footer.contact_soon') }}</p>
            @endif
        </section>

        @if ($social !== [])
            <section aria-labelledby="footer-follow">
                <h2 id="footer-follow" class="text-base font-semibold">{{ __('footer.follow') }}</h2>
                <ul class="mt-4 space-y-2">
                    @foreach ($social as $platform => $link)
                        <li>
                            <a href="{{ $link['url'] }}" rel="noopener me" class="text-link underline-offset-4 hover:underline">{{ $link['name'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>

    <div class="border-t border-line">
        <div class="mx-auto flex max-w-page flex-wrap items-center justify-between gap-2 px-4 py-4 text-xs text-ink-muted sm:px-6">
            <p>{{ __('footer.copyright', ['years' => $years, 'name' => __('site.name')]) }}</p>
            @unless (app()->environment('production'))
                <p>{{ __('footer.test_site') }}</p>
            @endunless
        </div>
    </div>
</footer>
