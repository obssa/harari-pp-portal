@use('App\Domain\Organisation\Queries\PublicDirectory')
@php $locale = app()->getLocale(); @endphp
<x-layouts.public :title="__('leagues.title')" :description="__('leagues.description')">
    <div class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <h1 class="heading-1">{{ __('leagues.title') }}</h1>
        <p class="mt-2 max-w-prose text-ink-muted">{{ __('leagues.description') }}</p>

        @if ($leagues->isEmpty())
            <p class="mt-10 rounded-card border border-line bg-surface px-5 py-8 text-center text-ink-muted">{{ __('directory.empty') }}</p>
        @else
            <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($leagues as $league)
                    @php $shown = PublicDirectory::shown($league, $locale); @endphp
                    @continue($shown === null)
                    <li lang="{{ $shown->locale }}">
                        <x-ui.card :title="$shown->getAttribute('name')" :href="route('leagues.show', ['locale' => $shown->locale, 'slug' => $shown->slug])" :media="$league->coverImage ?? $league->logo" :heading-level="2">
                            {{ $shown->getAttribute('summary') }}
                        </x-ui.card>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.public>
