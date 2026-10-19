<x-filament-widgets::widget>
    <x-filament::section :heading="$this::title()" icon="heroicon-o-magnifying-glass">
        @php $insights = $this->insights(); @endphp
        <div style="display: grid; gap: 1.5rem; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr))">
            @foreach (['top' => __('dashboard.search.top'), 'empty' => __('dashboard.search.empty')] as $list => $heading)
                <div>
                    <h3 style="font-size: .875rem; font-weight: 500; opacity: .7">{{ $heading }}</h3>
                    <ol style="margin-top: .5rem; font-size: .875rem">
                        @forelse ($insights[$list] as $term)
                            <li style="display: flex; justify-content: space-between; gap: .75rem; padding: .125rem 0" lang="{{ $term->locale }}">
                                <span>{{ $term->term }}</span>
                                <span style="font-variant-numeric: tabular-nums; opacity: .7">{{ $term->searches }}</span>
                            </li>
                        @empty
                            <li style="opacity: .7">{{ __('dashboard.search.none') }}</li>
                        @endforelse
                    </ol>
                </div>
            @endforeach
        </div>
        <p style="margin-top: 1rem; font-size: .875rem">
            <a href="{{ \App\Filament\Resources\SearchTerms\SearchTermResource::getUrl() }}" style="text-decoration: underline; text-underline-offset: 3px">{{ __('dashboard.search.all') }}</a>
        </p>
    </x-filament::section>
</x-filament-widgets::widget>
