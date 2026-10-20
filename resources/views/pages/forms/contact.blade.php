@use('App\Domain\Organisation\Queries\PublicDirectory')
@php
    $locale = app()->getLocale();
    $tel = fn (string $number): string => preg_replace('/[^0-9+]/', '', $number);
    $address = $settings->address[$locale] ?? $settings->address['am'] ?? $settings->address['en'] ?? null;
    $hours = $settings->office_hours[$locale] ?? $settings->office_hours['am'] ?? $settings->office_hours['en'] ?? null;
@endphp
<x-layouts.public :title="__('forms.contact_title')" :description="__('forms.contact_description')">
    <div class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <h1 class="heading-1">{{ __('forms.contact_title') }}</h1>
        <p class="mt-2 max-w-prose text-ink-muted">{{ __('forms.contact_description') }}</p>

        <div class="mt-8 grid gap-10 lg:grid-cols-3">
            <div class="lg:col-span-2">
                @if ($form && $translation)
                    <section aria-labelledby="contact-form">
                        <h2 id="contact-form" class="heading-2" lang="{{ $translation->locale }}">{{ $translation->title }}</h2>
                        @if (filled($translation->intro))
                            <p class="mt-2 max-w-prose whitespace-pre-line text-ink-muted">{{ $translation->intro }}</p>
                        @endif
                        <x-forms.public-form :form="$form" :translation="$translation" :turnstile-key="$turnstileKey" class="mt-6" />
                    </section>
                @endif

                @if ($offices->isNotEmpty())
                    <section aria-labelledby="contact-offices" class="mt-12">
                        <h2 id="contact-offices" class="heading-2">{{ __('forms.offices') }}</h2>
                        <ul class="mt-4 grid gap-4 sm:grid-cols-2">
                            @foreach ($offices as $office)
                                @php $shown = PublicDirectory::shown($office, $locale); @endphp
                                @continue($shown === null)
                                <li class="rounded-card border border-line bg-surface p-4" lang="{{ $shown->locale }}">
                                    <h3 class="font-semibold"><a href="{{ route('offices.show', ['locale' => $shown->locale, 'slug' => $shown->slug]) }}" class="underline-offset-4 hover:underline">{{ $shown->getAttribute('name') }}</a></h3>
                                    @if (filled($shown->getAttribute('address')))
                                        <p class="mt-1 whitespace-pre-line text-sm text-ink-muted">{{ $shown->getAttribute('address') }}</p>
                                    @endif
                                    <p class="mt-2 space-x-3 text-sm" dir="ltr">
                                        @if ($office->phone)<a href="tel:{{ $tel($office->phone) }}" class="text-link hover:underline">{{ $office->phone }}</a>@endif
                                        @if ($office->email)<a href="mailto:{{ $office->email }}" class="text-link hover:underline">{{ $office->email }}</a>@endif
                                    </p>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>

            <aside class="space-y-6 text-sm" aria-label="{{ __('footer.contact') }}">
                <dl class="space-y-3">
                    @if (filled($address))
                        <div>
                            <dt class="font-semibold">{{ __('forms.address') }}</dt>
                            <dd class="mt-0.5 whitespace-pre-line text-ink-muted">{{ $address }}</dd>
                        </div>
                    @endif
                    @if ($settings->hotline)
                        <div>
                            <dt class="font-semibold">{{ __('footer.hotline') }}</dt>
                            <dd class="mt-0.5"><a href="tel:{{ $tel($settings->hotline) }}" class="text-lg font-semibold text-link hover:underline" dir="ltr">{{ $settings->hotline }}</a></dd>
                        </div>
                    @endif
                    @if ($settings->contact_phone)
                        <div>
                            <dt class="font-semibold">{{ __('footer.phone') }}</dt>
                            <dd class="mt-0.5"><a href="tel:{{ $tel($settings->contact_phone) }}" class="text-link hover:underline" dir="ltr">{{ $settings->contact_phone }}</a></dd>
                        </div>
                    @endif
                    @if ($settings->contact_email)
                        <div>
                            <dt class="font-semibold">{{ __('forms.email') }}</dt>
                            <dd class="mt-0.5"><a href="mailto:{{ $settings->contact_email }}" class="text-link hover:underline" dir="ltr">{{ $settings->contact_email }}</a></dd>
                        </div>
                    @endif
                    @if (filled($hours))
                        <div>
                            <dt class="font-semibold">{{ __('forms.hours') }}</dt>
                            <dd class="mt-0.5 whitespace-pre-line text-ink-muted">{{ $hours }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($settings->social_links !== [])
                    <div>
                        <h2 class="font-semibold">{{ __('footer.follow') }}</h2>
                        <ul class="mt-2 space-y-1">
                            @foreach ($settings->social_links as $platform => $link)
                                <li><a href="{{ $link }}" rel="noopener" class="text-link underline-offset-4 hover:underline">{{ \Illuminate\Support\Str::headline((string) $platform) }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <p><a href="{{ route('tickets.lookup') }}" class="text-link underline-offset-4 hover:underline">{{ __('forms.lookup_link') }}</a></p>
            </aside>
        </div>
    </div>
</x-layouts.public>
