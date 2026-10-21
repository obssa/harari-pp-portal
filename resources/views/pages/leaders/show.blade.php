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

            <x-directory.posts :posts="$posts" :heading="__('leaders.posts')" />
        </div>
    </article>
</x-layouts.public>
