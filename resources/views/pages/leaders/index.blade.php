@use('App\Domain\Organisation\Queries\PublicDirectory')
@php $locale = app()->getLocale(); @endphp
<x-layouts.public :title="__('leaders.title')" :description="__('leaders.description')">
    <div class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <h1 class="heading-1">{{ __('leaders.title') }}</h1>
        <p class="mt-2 max-w-prose text-ink-muted">{{ __('leaders.description') }}</p>

        @forelse ($groups as $group)
            <section class="mt-12" aria-labelledby="body-{{ $group['body']->value }}">
                <h2 id="body-{{ $group['body']->value }}" class="heading-2">{{ $group['body']->label() }}</h2>
                <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
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
