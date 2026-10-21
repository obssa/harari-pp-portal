@use('App\Domain\Content\Queries\PublicPosts')
@php $locale = app()->getLocale(); @endphp
<x-layouts.public :title="__('press.title')" :description="__('press.description')">
    <x-page-hero :title="__('press.title')" :lead="__('press.description')" tone="plaster" :crumbs="[[__('nav.media'), route('news.index')]]" />
    <div class="mx-auto max-w-page px-4 pt-2 pb-12 sm:px-6">

        <div class="mt-10 grid gap-10 lg:grid-cols-3">
            <div class="space-y-12 lg:col-span-2">
                @if ($boilerplate)
                    <section aria-labelledby="press-about">
                        <h2 id="press-about" class="heading-2">{{ __('press.about') }}</h2>
                        <p class="mt-3 max-w-prose whitespace-pre-line">{{ $boilerplate }}</p>
                    </section>
                @endif

                @if ($logos->isNotEmpty())
                    <section aria-labelledby="press-logos">
                        <h2 id="press-logos" class="heading-2">{{ __('press.logos') }}</h2>
                        <ul class="mt-4 grid gap-4 sm:grid-cols-3">
                            @foreach ($logos as $logo)
                                <li class="rounded-card border border-line bg-surface p-4">
                                    <div class="grid h-28 place-items-center rounded bg-white p-3">
                                        <img src="{{ route('media.file', ['uuid' => $logo->uuid, 'name' => $logo->file_name]) }}" alt="{{ $logo->altFor($locale) ?: __('press.logo_alt') }}" loading="lazy" class="max-h-full max-w-full object-contain">
                                    </div>
                                    <a href="{{ route('media.file', ['uuid' => $logo->uuid, 'name' => $logo->file_name]) }}" download class="mt-3 block text-sm text-link underline-offset-4 hover:underline">
                                        {{ __('press.download_file', ['type' => strtoupper(pathinfo($logo->file_name, PATHINFO_EXTENSION))]) }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                <section aria-labelledby="press-colours">
                    <h2 id="press-colours" class="heading-2">{{ __('press.colours') }}</h2>
                    <ul class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                        @foreach ($palette as $name => $hex)
                            <li>
                                <span aria-hidden="true" class="block h-16 rounded-card border border-line" style="background-color: {{ $hex }}"></span>
                                <span class="mt-2 block text-sm font-medium">{{ __("press.colour.{$name}") }}</span>
                                <span class="block font-mono text-sm text-ink-muted" dir="ltr">{{ $hex }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>

                @if ($leaders->isNotEmpty())
                    <section aria-labelledby="press-portraits">
                        <h2 id="press-portraits" class="heading-2">{{ __('press.portraits') }}</h2>
                        <ul class="mt-4 grid gap-4 sm:grid-cols-3">
                            @foreach ($leaders as $item)
                                @php $portrait = $item['leader']->portrait; @endphp
                                <li class="rounded-card border border-line bg-surface p-3" lang="{{ $item['translation']->locale }}">
                                    <x-picture :media="$portrait" preset="square" sizes="(min-width: 40rem) 14rem, 50vw" class="aspect-square w-full rounded object-cover" />
                                    <p class="mt-2 font-medium">{{ $item['translation']->getAttribute('name') }}</p>
                                    <a href="{{ route('media.file', ['uuid' => $portrait->uuid, 'name' => $portrait->file_name]) }}" download class="text-sm text-link underline-offset-4 hover:underline">{{ __('press.download_portrait') }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($releases->isNotEmpty())
                    <section aria-labelledby="press-releases">
                        <h2 id="press-releases" class="heading-2">{{ __('press.releases') }}</h2>
                        <ul class="mt-4 divide-y divide-line rounded-card border border-line bg-surface">
                            @foreach ($releases as $post)
                                @php $shown = PublicPosts::shown($post, $locale); @endphp
                                @continue($shown === null)
                                <li lang="{{ $shown->locale }}" class="flex flex-col gap-1 p-4 sm:flex-row sm:items-baseline sm:gap-4">
                                    <span class="shrink-0 text-xs text-ink-muted"><x-date :date="$shown->published_at" /></span>
                                    <a href="{{ route('news.show', ['locale' => $shown->locale, 'slug' => $shown->slug]) }}" class="font-medium underline-offset-4 hover:underline">{{ $shown->title }}</a>
                                </li>
                            @endforeach
                        </ul>
                        <p class="mt-3 text-sm"><a href="{{ route('news.index', ['type' => 'press_release']) }}" class="text-link underline-offset-4 hover:underline">{{ __('homepage.all_press') }}</a></p>
                    </section>
                @endif
            </div>

            <aside class="space-y-4 text-sm" aria-labelledby="press-contact">
                <div class="rounded-card border border-line bg-surface p-4">
                    <h2 id="press-contact" class="heading-3">{{ __('press.contact') }}</h2>
                    @if ($contactName)<p class="mt-2 font-medium">{{ $contactName }}</p>@endif
                    @if ($settings->contact_phone)
                        <p class="mt-1"><a href="tel:{{ preg_replace('/[^0-9+]/', '', $settings->contact_phone) }}" class="text-link hover:underline" dir="ltr">{{ $settings->contact_phone }}</a></p>
                    @endif
                    @if ($settings->contact_email)
                        <p class="mt-1"><a href="mailto:{{ $settings->contact_email }}" class="text-link hover:underline" dir="ltr">{{ $settings->contact_email }}</a></p>
                    @endif
                    @if ($form)
                        <a href="{{ route('forms.show', ['locale' => $form->locale, 'slug' => $form->slug]) }}" class="mt-4 inline-flex rounded-control bg-primary-600 px-4 py-2 font-semibold text-white hover:bg-primary-700">{{ __('press.enquiry') }}</a>
                    @endif
                    @if (! $contactName && ! $settings->contact_phone && ! $settings->contact_email && ! $form)
                        <p class="mt-2 text-ink-muted"><a href="{{ route('contact') }}" class="text-link hover:underline">{{ __('press.contact_page') }}</a></p>
                    @endif
                </div>
            </aside>
        </div>
    </div>
</x-layouts.public>
