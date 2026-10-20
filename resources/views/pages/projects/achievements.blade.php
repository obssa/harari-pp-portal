@use('App\Domain\Organisation\Queries\PublicDirectory')
@use('App\Domain\Projects\Enums\ProjectStatus')
@php $locale = app()->getLocale(); @endphp
<x-layouts.public :title="__('projects.achievements_title')" :description="__('projects.achievements_description')">
    <div class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <div class="flex flex-wrap items-baseline justify-between gap-3">
            <h1 class="heading-1">{{ __('projects.achievements_title') }}</h1>
            <a href="{{ route('projects.index') }}" class="text-sm font-medium text-link underline-offset-4 hover:underline">{{ __('projects.all_projects') }}</a>
        </div>
        <p class="mt-2 max-w-prose text-ink-muted">{{ __('projects.achievements_description') }}</p>

        @if ($total === 0 && $regional->isEmpty() && $sectors->isEmpty())
            <p class="mt-10 rounded-card border border-line bg-surface px-5 py-8 text-center text-ink-muted">{{ __('directory.empty') }}</p>
        @endif

        @if ($total > 0)
            <section aria-labelledby="achievements-projects" class="mt-8">
                <h2 id="achievements-projects" class="sr-only">{{ __('projects.by_status') }}</h2>
                <ul class="grid gap-4 sm:grid-cols-4">
                    @foreach (ProjectStatus::cases() as $status)
                        <li class="rounded-card border border-line bg-surface p-4">
                            <p class="text-3xl font-bold tabular-nums text-primary-700 dark:text-primary-300">{{ $counts[$status->value] }}</p>
                            <p class="mt-1 font-medium">{{ __('projects.count.'.$status->value) }}</p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($regional->isNotEmpty())
            <section aria-labelledby="achievements-regional" class="mt-12">
                <h2 id="achievements-regional" class="heading-2">{{ __('projects.regional') }}</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($regional as $statistic)
                        <x-statistic-tile :statistic="$statistic" />
                    @endforeach
                </div>
            </section>
        @endif

        @foreach ($sectors as $index => $item)
            <section aria-labelledby="sector-{{ $index }}" class="mt-12 border-t border-line pt-10">
                <h2 id="sector-{{ $index }}" class="heading-2">{{ $item['name'] }}</h2>

                <div class="mt-4 grid gap-8 lg:grid-cols-3">
                    <div class="lg:col-span-2">
                        @if ($item['statistics']->isNotEmpty())
                            <div class="grid gap-4 sm:grid-cols-2">
                                @foreach ($item['statistics'] as $statistic)
                                    <x-statistic-tile :statistic="$statistic" />
                                @endforeach
                            </div>
                        @endif

                        @if ($item['completed']->isNotEmpty())
                            <h3 class="heading-3 mt-8">{{ __('projects.recently_completed') }}</h3>
                            <ul class="mt-4 grid gap-4 sm:grid-cols-3">
                                @foreach ($item['completed'] as $project)
                                    @php $shown = PublicDirectory::shown($project, $locale); @endphp
                                    @continue($shown === null)
                                    <li class="flex"><x-project-card :project="$project" :translation="$shown" :heading-level="4" class="w-full" /></li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    @if ($item['total'] > 0)
                        <figure class="rounded-card border border-line bg-surface p-4">
                            <figcaption class="font-semibold">{{ __('projects.chart_title', ['count' => $item['total']]) }}</figcaption>
                            <ul class="mt-4 space-y-3 text-sm">
                                @foreach (ProjectStatus::cases() as $status)
                                    @php $share = $item['total'] > 0 ? round($item['counts'][$status->value] / $item['total'] * 100) : 0; @endphp
                                    <li>
                                        <span class="flex justify-between"><span>{{ $status->label() }}</span><span class="tabular-nums">{{ $item['counts'][$status->value] }}</span></span>
                                        <span aria-hidden="true" class="mt-1 block h-2 overflow-hidden rounded-full bg-surface-muted"><span class="block h-full rounded-full bg-primary-600" style="width: {{ $share }}%"></span></span>
                                    </li>
                                @endforeach
                            </ul>
                            <a href="{{ route('projects.index', ['sector' => $item['sector']->id]) }}" class="mt-4 inline-block text-sm text-link underline-offset-4 hover:underline">{{ __('projects.sector_projects') }}</a>
                        </figure>
                    @endif
                </div>
            </section>
        @endforeach
    </div>
</x-layouts.public>
