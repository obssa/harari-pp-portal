@use('App\Domain\Organisation\Queries\PublicDirectory')
@php $locale = app()->getLocale(); @endphp
<x-layouts.public :title="__('leagues.title')" :description="__('leagues.description')">
    <x-page-hero :title="__('leagues.title')" :lead="__('leagues.description')" tone="plaster" :crumbs="[]" />
    <div class="mx-auto max-w-page px-4 pt-2 pb-12 sm:px-6">

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
