@php $locale = app()->getLocale(); @endphp
<x-layouts.public
    :title="$translation->name"
    :description="$translation->summary ?: $translation->position"
    :alternates="$alternates"
    :image="$image"
    type="profile"
    :json-ld="$jsonLd"
>
    <article>
        {{-- ADR-0062: the person on the plaster band, portrait in a gold ring. --}}
        <div class="bg-surface-muted">
            <div class="mx-auto max-w-page px-4 pt-8 pb-10 sm:px-6">
                <nav aria-label="{{ __('news.breadcrumb') }}" class="text-sm text-ink-muted">
                    <ol class="flex flex-wrap gap-1">
                        <li><a href="{{ route('home') }}" class="hover:underline">{{ __('nav.home') }}</a> /</li>
                        <li><a href="{{ route('leaders.index') }}" class="hover:underline">{{ __('leaders.title') }}</a> /</li>
                        <li>{{ $leader->body->label() }}</li>
                    </ol>
                </nav>

                <header class="mt-8 flex flex-wrap items-center gap-8">
                    @if ($leader->portrait)
                        <x-picture :media="$leader->portrait" preset="square" sizes="12rem" loading="eager" fetchpriority="high" class="size-40 shrink-0 rounded-full object-cover ring-6 ring-gold-500 sm:size-48" />
                    @else
                        <span aria-hidden="true" class="flex size-40 shrink-0 items-center justify-center rounded-full bg-primary-900 text-5xl font-extrabold text-white ring-6 ring-gold-500 sm:size-48">{{ mb_substr($translation->name, 0, 1) }}</span>
                    @endif
                    <div class="min-w-0 flex-1 basis-80">
                        <p class="kicker">{{ $leader->body->label() }}</p>
                        <h1 class="heading-1 mt-2">{{ $translation->name }}</h1>
                        @if ($translation->position)
                            <p class="mt-3 max-w-prose text-lg text-ink-muted">{{ $translation->position }}</p>
                        @endif
                        <ul class="mt-4 flex flex-wrap gap-2">
                            @if ($office)
                                <li><a href="{{ route('offices.show', ['locale' => $office->locale, 'slug' => $office->slug]) }}"><x-ui.badge tone="primary">{{ $office->getAttribute('name') }}</x-ui.badge></a></li>
                            @endif
                            @if ($league)
                                <li><a href="{{ route('leagues.show', ['locale' => $league->locale, 'slug' => $league->slug]) }}"><x-ui.badge tone="magenta">{{ $league->getAttribute('name') }}</x-ui.badge></a></li>
                            @endif
                        </ul>
                    </div>
                </header>
            </div>
        </div>

        <div class="mx-auto max-w-page px-4 pt-2 pb-12 sm:px-6">
            <div class="mt-10 grid gap-10 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    @if ($translation->summary)
                        <p class="max-w-prose text-lg">{{ $translation->summary }}</p>
                    @endif
                    @if (trim($body) !== '')
                        <div class="prose-content mt-6 max-w-prose">{!! $body !!}</div>
                    @endif
                </div>

                <aside class="space-y-6 lg:col-span-1">
                    @if ($leader->term_start || $leader->term_end)
                        <p class="text-sm text-ink-muted">
                            {{ __('leaders.term') }}:
                            @if ($leader->term_start)<x-date :date="$leader->term_start" />@endif
                            –
                            @if ($leader->term_end)<x-date :date="$leader->term_end" />@else{{ __('leaders.present') }}@endif
                        </p>
                    @endif
                    <x-directory.contacts :phone="$leader->phone" :email="$leader->email" :socials="$leader->socialLinks()" />
                    @if ($leader->source_url)
                        {{-- ADR-0063: where the position was published, so readers can check it. --}}
                        <p class="rounded-card border border-line bg-surface p-4 text-sm text-ink-muted">
                            {{ __('leaders.source') }}:
                            <a href="{{ $leader->source_url }}" rel="noopener nofollow" class="break-all text-link underline-offset-4 hover:underline">{{ preg_replace('#^https?://(www\.)?#', '', $leader->source_url) }}</a>@if ($leader->source_date), <x-date :date="$leader->source_date" />@endif
                        </p>
                    @endif
                </aside>
            </div>

            @php $photos = $leader->photos(); @endphp
            @if ($photos->isNotEmpty())
                {{-- ADR-0071: the profile's photos; each opens large over the page, without script. --}}
                <section aria-labelledby="leader-photos" class="mt-14">
                    <h2 id="leader-photos" class="heading-2">{{ __('leaders.photos') }}</h2>
                    <ul class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        @foreach ($photos as $number => $photo)
                            <li>
                                <a href="#leader-photo-{{ $number + 1 }}" class="group block overflow-hidden rounded-card bg-surface-muted ring-1 ring-line focus-visible:outline-3 focus-visible:outline-gold-500">
                                    <x-picture :media="$photo" preset="square" sizes="(min-width: 64rem) 18rem, 50vw" class="aspect-square w-full object-cover transition-transform duration-500 group-hover:scale-105" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    @foreach ($photos as $number => $photo)
                        <div id="leader-photo-{{ $number + 1 }}" role="dialog" aria-modal="true" aria-label="{{ $photo->altFor() ?: __('leaders.photos') }}"
                             class="fixed inset-0 z-50 hidden place-items-center bg-primary-950/90 p-4 target:grid">
                            <figure class="max-h-full max-w-5xl">
                                <img src="{{ $photo->getUrl() }}" alt="{{ $photo->altFor() }}" loading="lazy" decoding="async" class="max-h-[80vh] w-auto rounded-card object-contain shadow-2xl">
                                <figcaption class="mt-3 flex flex-wrap items-center justify-between gap-3 text-sm text-white/85">
                                    <span>{{ $photo->altFor() }}@if (filled($photo->credit)) · {{ __('content.photo_credit', ['credit' => $photo->credit]) }}@endif</span>
                                    <span class="flex gap-2">
                                        @if ($number > 0)<a href="#leader-photo-{{ $number }}" class="rounded-full px-3 py-1.5 ring-1 ring-white/40 hover:bg-white/10">{{ __('leaders.photo_previous') }}</a>@endif
                                        @if ($number < $photos->count() - 1)<a href="#leader-photo-{{ $number + 2 }}" class="rounded-full px-3 py-1.5 ring-1 ring-white/40 hover:bg-white/10">{{ __('leaders.photo_next') }}</a>@endif
                                        <a href="#leader-photos" class="rounded-full bg-gold-500 px-3 py-1.5 font-semibold text-ink-brand hover:bg-gold-400">{{ __('leaders.photo_close') }}</a>
                                    </span>
                                </figcaption>
                            </figure>
                        </div>
                    @endforeach
                </section>
            @endif

            <x-directory.posts :posts="$posts" :heading="__('leaders.posts')" />
        </div>
    </article>
</x-layouts.public>
