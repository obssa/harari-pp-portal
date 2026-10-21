@php
    $select = 'mt-1 block w-full rounded-control border border-line bg-surface px-3 py-2';
    $chip = 'inline-block rounded-full border px-3 py-1';
    $active = 'border-primary-600 bg-primary-50 text-primary-800 dark:bg-primary-950 dark:text-primary-200';
@endphp
<x-layouts.public :title="__('social.hub.title')" :description="__('social.hub.description')">
    <x-page-hero :title="__('social.hub.title')" :lead="__('social.hub.description')" tone="ink" :crumbs="[[__('nav.media'), route('news.index')]]" />
    <div class="mx-auto max-w-page px-4 pt-2 pb-12 sm:px-6">

        @if ($platforms->count() > 1)
            <nav aria-label="{{ __('social.hub.networks') }}" class="mt-6">
                <ul class="flex flex-wrap gap-2 text-sm">
                    <li><a href="{{ route('social', array_filter($filters)) }}" @if (! $platform) aria-current="page" @endif @class([$chip, $active => ! $platform, 'border-line hover:bg-surface-muted' => (bool) $platform])>{{ __('news.all') }}</a></li>
                    @foreach ($platforms as $option)
                        <li><a href="{{ route('social', [...array_filter($filters), 'platform' => $option->value]) }}" @if ($platform === $option) aria-current="page" @endif
                               @class([$chip, $active => $platform === $option, 'border-line hover:bg-surface-muted' => $platform !== $option])>{{ $option->label() }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif

        @if ($offices !== [] || $leagues !== [])
            <form method="get" action="{{ route('social') }}" aria-label="{{ __('social.hub.filter') }}" class="mt-4 flex flex-wrap items-end gap-4">
                @if ($platform)<input type="hidden" name="platform" value="{{ $platform->value }}">@endif
                @if ($offices !== [])
                    <div>
                        <label for="filter-office" class="text-sm font-medium">{{ __('documents.filter_office') }}</label>
                        <select id="filter-office" name="office" class="{{ $select }}">
                            <option value="">{{ __('documents.all_offices') }}</option>
                            @foreach ($offices as $id => $name)
                                <option value="{{ $id }}" @selected($filters['office'] === (string) $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                @if ($leagues !== [])
                    <div>
                        <label for="filter-league" class="text-sm font-medium">{{ __('admin.leaders.field.league') }}</label>
                        <select id="filter-league" name="league" class="{{ $select }}">
                            <option value="">{{ __('social.hub.all_leagues') }}</option>
                            @foreach ($leagues as $id => $name)
                                <option value="{{ $id }}" @selected($filters['league'] === (string) $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <button type="submit" class="rounded-control bg-primary-600 px-4 py-2 font-semibold text-white hover:bg-primary-700">{{ __('documents.apply') }}</button>
            </form>
        @endif

        <div class="mt-8 grid gap-10 lg:grid-cols-4">
            <div class="lg:col-span-3">
                @if ($posts->isEmpty())
                    <p class="rounded-card border border-line bg-surface px-5 py-8 text-center text-ink-muted">{{ __('directory.empty') }}</p>
                @else
                    <ul class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($posts as $post)
                            <li class="flex"><x-social-post :post="$post" :heading-level="2" class="w-full" /></li>
                        @endforeach
                    </ul>
                    <div class="mt-10">{{ $posts->links() }}</div>
                @endif
            </div>

            @if ($follow->isNotEmpty())
                <aside aria-labelledby="social-follow">
                    <h2 id="social-follow" class="heading-3">{{ __('footer.follow') }}</h2>
                    <ul class="mt-4 space-y-4">
                        @foreach ($follow as $item)
                            <li class="rounded-card border border-line bg-surface p-4 text-sm">
                                <p class="font-medium">{{ $item['source']->name }}</p>
                                <p class="text-ink-muted">{{ $item['source']->platform->label() }}</p>
                                <a href="{{ $item['source']->url() }}" rel="noopener me" class="mt-2 inline-flex rounded-control border border-line px-3 py-1.5 hover:bg-surface-muted">{{ __('social.hub.follow_on', ['platform' => $item['source']->platform->label()]) }}</a>
                                @if ($item['qr'])
                                    <img src="{{ $item['qr'] }}" alt="{{ __('social.hub.qr_alt', ['name' => $item['source']->name]) }}" width="128" height="128" loading="lazy" class="mt-3 size-32 rounded bg-white p-1">
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </aside>
            @endif
        </div>
    </div>
</x-layouts.public>
