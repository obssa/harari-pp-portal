@use('App\Domain\Search\SiteSearch')
@php $title = $searched ? __('search.title_for', ['query' => $query]) : __('search.title'); @endphp
<x-layouts.public :title="$title" :description="__('search.description')">
    <x-page-hero :title="__('search.title')" :lead="__('search.description')" tone="night">
        <form method="get" action="{{ route('search') }}" role="search" class="flex max-w-3xl flex-wrap gap-2">
            @if ($kind)
                <input type="hidden" name="type" value="{{ $kind }}">
            @endif
            <label for="search-page-q" class="sr-only">{{ __('search.label') }}</label>
            <input id="search-page-q" type="search" name="q" value="{{ $query }}" maxlength="100" required minlength="2"
                   placeholder="{{ __('search.placeholder') }}" class="min-w-0 flex-1 rounded-full border-0 bg-white px-5 py-3 text-base text-ink-brand placeholder:text-ink-muted focus:outline-none focus:ring-3 focus:ring-gold-400">
            <x-ui.button type="submit" variant="gold" size="lg">{{ __('search.button') }}</x-ui.button>
        </form>
    </x-page-hero>
    <div class="mx-auto max-w-page px-4 pt-2 pb-12 sm:px-6">

        @if (! $searched)
            <p class="mt-6 max-w-prose text-ink-muted">{{ __('search.hint') }}</p>
        @else
            <p class="mt-6 text-sm text-ink-muted" role="status">
                {{ $total === 0 ? __('search.none', ['query' => $query]) : __('search.results_for', ['query' => $query]) }}
            </p>

            @if ($kind)
                <p class="mt-2 text-sm"><a href="{{ route('search', ['q' => $query]) }}" class="text-link underline-offset-4 hover:underline">{{ __('search.all_kinds') }}</a></p>
            @endif

            @if ($total === 0)
                <div class="mt-6 max-w-prose rounded-card border border-line bg-surface p-5">
                    <p>{{ __('search.tips') }}</p>
                    <ul class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm">
                        <li><a href="{{ route('news.index') }}" class="text-link underline-offset-4 hover:underline">{{ __('nav.news') }}</a></li>
                        <li><a href="{{ route('leaders.index') }}" class="text-link underline-offset-4 hover:underline">{{ __('leaders.title') }}</a></li>
                        <li><a href="{{ route('offices.index') }}" class="text-link underline-offset-4 hover:underline">{{ __('offices.title') }}</a></li>
                    </ul>
                </div>
            @endif

            @foreach ($results as $name => $found)
                @continue($found->isEmpty())
                @php $limit = $kind ? SiteSearch::PER_KIND_ALONE : SiteSearch::PER_KIND; @endphp
                <section aria-labelledby="results-{{ $name }}" class="mt-10">
                    <h2 id="results-{{ $name }}" class="heading-3">{{ __("search.kind.{$name}") }}</h2>
                    <ul class="mt-4 divide-y divide-line rounded-card border border-line bg-surface">
                        @foreach ($found->take($limit) as $translation)
                            @php $item = SiteSearch::present($translation); @endphp
                            @continue($item === null)
                            <li lang="{{ $item['locale'] }}" class="p-4">
                                <p class="text-xs text-ink-muted">{{ $item['label'] }}</p>
                                <a href="{{ $item['url'] }}" class="mt-1 block font-semibold underline-offset-4 hover:underline">{{ $item['title'] }}</a>
                                @if (filled($item['summary']))
                                    <p class="mt-1 text-sm text-ink-muted">{{ \Illuminate\Support\Str::limit($item['summary'], 200) }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @if (! $kind && $found->count() > SiteSearch::PER_KIND)
                        <p class="mt-3 text-sm"><a href="{{ route('search', ['q' => $query, 'type' => $name]) }}" class="text-link underline-offset-4 hover:underline">{{ __('search.more', ['kind' => __("search.kind.{$name}")]) }}</a></p>
                    @endif
                </section>
            @endforeach
        @endif
    </div>
</x-layouts.public>
