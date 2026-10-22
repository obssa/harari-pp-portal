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

        <div class="mt-8 grid gap-10 lg:grid-cols-3">
            <div class="lg:col-span-2">
                @if ($posts->isEmpty())
                    <p class="rounded-card border border-line bg-surface px-5 py-8 text-center text-ink-muted">{{ __('directory.empty') }}</p>
                @else
                    <ul class="grid gap-6 sm:grid-cols-2">
                        @foreach ($posts as $post)
                            <li class="flex"><x-social-post :post="$post" :heading-level="2" class="w-full" /></li>
                        @endforeach
                    </ul>
                    <div class="mt-10">{{ $posts->links() }}</div>
                @endif
            </div>

            @php
                $facebookFeed = \App\Support\SocialLinks::facebookFeed();
                $socialLinks = array_diff_key(\App\Support\SocialLinks::all(), $facebookFeed !== null ? ['facebook' => true] : []);
                $codes = $follow->filter(fn (array $item): bool => (bool) $item['qr']);
            @endphp
            @if ($socialLinks !== [] || $facebookFeed !== null || $codes->isNotEmpty())
                {{-- ADR-0072: the party's pages, its Facebook page and the codes to scan, beside the gathered posts. --}}
                <aside aria-labelledby="social-follow" class="space-y-6">
                    <h2 id="social-follow" class="heading-3">{{ __('footer.follow') }}</h2>
                    @if ($socialLinks !== [])
                        <x-social.platforms :links="$socialLinks" stacked />
                    @endif
                    @if ($facebookFeed !== null)
                        <x-facebook-feed :page="$facebookFeed['page']" :mode="$facebookFeed['mode']" :height="560" />
                    @endif
                    @if ($codes->isNotEmpty())
                        <ul class="grid grid-cols-2 gap-4">
                            @foreach ($codes as $item)
                                <li class="rounded-card border border-line bg-surface p-3 text-center text-sm">
                                    <img src="{{ $item['qr'] }}" alt="{{ __('social.hub.qr_alt', ['name' => $item['source']->name]) }}" width="128" height="128" loading="lazy" class="mx-auto size-28 rounded bg-white p-1">
                                    <p class="mt-2 font-semibold">{{ $item['source']->platform->label() }}</p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </aside>
            @endif
        </div>
    </div>
</x-layouts.public>
