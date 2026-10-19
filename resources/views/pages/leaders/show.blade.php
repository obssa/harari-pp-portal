@php $locale = app()->getLocale(); @endphp
<x-layouts.public
    :title="$translation->name"
    :description="$translation->summary ?: $translation->position"
    :alternates="$alternates"
    :image="$image"
    type="profile"
    :json-ld="$jsonLd"
>
    <article class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <nav aria-label="{{ __('news.breadcrumb') }}" class="text-sm text-ink-muted">
            <ol class="flex flex-wrap gap-1">
                <li><a href="{{ route('home') }}" class="hover:underline">{{ __('nav.home') }}</a> /</li>
                <li><a href="{{ route('leaders.index') }}" class="hover:underline">{{ __('leaders.title') }}</a> /</li>
                <li>{{ $leader->body->label() }}</li>
            </ol>
        </nav>

        <div class="mt-8 grid gap-10 lg:grid-cols-3">
            <header class="lg:col-span-1">
                @if ($leader->portrait)
                    <x-picture :media="$leader->portrait" preset="square" sizes="(min-width: 64rem) 20rem, 60vw" loading="eager" fetchpriority="high" class="w-full max-w-xs rounded-card" />
                @endif
                <h1 class="heading-1 mt-6">{{ $translation->name }}</h1>
                @if ($translation->position)
                    <p class="mt-2 text-lg text-ink-muted">{{ $translation->position }}</p>
                @endif
                <ul class="mt-4 flex flex-wrap gap-2">
                    <li><x-ui.badge tone="primary">{{ $leader->body->label() }}</x-ui.badge></li>
                    @if ($office)
                        <li><a href="{{ route('offices.show', ['locale' => $office->locale, 'slug' => $office->slug]) }}"><x-ui.badge>{{ $office->getAttribute('name') }}</x-ui.badge></a></li>
                    @endif
                    @if ($league)
                        <li><a href="{{ route('leagues.show', ['locale' => $league->locale, 'slug' => $league->slug]) }}"><x-ui.badge>{{ $league->getAttribute('name') }}</x-ui.badge></a></li>
                    @endif
                </ul>
                @if ($leader->term_start || $leader->term_end)
                    <p class="mt-4 text-sm text-ink-muted">
                        {{ __('leaders.term') }}:
                        @if ($leader->term_start)<x-date :date="$leader->term_start" />@endif
                        –
                        @if ($leader->term_end)<x-date :date="$leader->term_end" />@else{{ __('leaders.present') }}@endif
                    </p>
                @endif
                <x-directory.contacts class="mt-6" :phone="$leader->phone" :email="$leader->email" :socials="$leader->socialLinks()" />
            </header>

            <div class="lg:col-span-2">
                @if ($translation->summary)
                    <p class="max-w-prose text-lg">{{ $translation->summary }}</p>
                @endif
                @if (trim($body) !== '')
                    <div class="prose-content mt-6 max-w-prose">{!! $body !!}</div>
                @endif
            </div>
        </div>

        <x-directory.posts :posts="$posts" :heading="__('leaders.posts')" />
    </article>
</x-layouts.public>
