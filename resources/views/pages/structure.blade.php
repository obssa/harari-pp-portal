<x-layouts.public :title="__('structure.title')" :description="__('structure.description')">
    <div class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <h1 class="heading-1">{{ __('structure.title') }}</h1>
        <p class="mt-2 max-w-prose text-ink-muted">{{ __('structure.description') }}</p>

        @if ($tree === [])
            <p class="mt-10 rounded-card border border-line bg-surface px-5 py-8 text-center text-ink-muted">{{ __('directory.empty') }}</p>
        @else
            <ul class="mt-8 space-y-3">
                @foreach ($tree as $node)
                    @include('pages.partials.structure-node', ['node' => $node, 'level' => 1])
                @endforeach
            </ul>
        @endif

        <p class="mt-12 flex flex-wrap gap-x-6 gap-y-2 text-sm">
            <a href="{{ route('leaders.index') }}" class="text-link underline-offset-4 hover:underline">{{ __('leaders.title') }}</a>
            <a href="{{ route('offices.index') }}" class="text-link underline-offset-4 hover:underline">{{ __('offices.title') }}</a>
        </p>
    </div>
</x-layouts.public>
