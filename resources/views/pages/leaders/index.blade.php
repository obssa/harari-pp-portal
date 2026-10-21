@use('App\Domain\Organisation\Queries\PublicDirectory')
@php $locale = app()->getLocale(); @endphp
<x-layouts.public :title="__('leaders.title')" :description="__('leaders.description')">
    <x-page-hero :title="__('leaders.title')" :lead="__('leaders.description')" tone="plaster" :crumbs="[[__('nav.about'), route('structure')]]" />
    <div class="mx-auto max-w-page px-4 pt-2 pb-12 sm:px-6">

        @forelse ($groups as $group)
            <section class="mt-12" aria-labelledby="body-{{ $group['body']->value }}">
                <h2 id="body-{{ $group['body']->value }}" class="heading-2">{{ $group['body']->label() }}</h2>
                <ul class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($group['leaders'] as $leader)
                        @php $shown = PublicDirectory::shown($leader, $locale); @endphp
                        @continue($shown === null)
                        <li><x-directory.person :leader="$leader" :translation="$shown" /></li>
                    @endforeach
                </ul>
            </section>
        @empty
            <p class="mt-10 rounded-card border border-line bg-surface px-5 py-8 text-center text-ink-muted">{{ __('directory.empty') }}</p>
        @endforelse

        <p class="mt-12 text-sm">
            <a href="{{ route('structure') }}" class="text-link underline-offset-4 hover:underline">{{ __('structure.title') }}</a>
        </p>
    </div>
</x-layouts.public>
