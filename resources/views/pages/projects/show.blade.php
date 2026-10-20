@php
    $budget = $project->publicBudget();
@endphp
<x-layouts.public :title="$translation->title" :description="$translation->summary" :alternates="$alternates" :image="$image" :json-ld="$jsonLd">
    <article class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <nav aria-label="{{ __('news.breadcrumb') }}" class="text-sm text-ink-muted">
            <ol class="flex flex-wrap gap-1">
                <li><a href="{{ route('home') }}" class="hover:underline">{{ __('nav.home') }}</a> /</li>
                <li><a href="{{ route('projects.index') }}" class="hover:underline">{{ __('projects.title') }}</a></li>
            </ol>
        </nav>

        <header class="mt-6 max-w-3xl">
            <p class="flex flex-wrap items-center gap-2 text-sm">
                <x-ui.badge :tone="$project->status->tone()">{{ $project->status->label() }}</x-ui.badge>
                @if ($project->sector)
                    <span class="text-ink-muted">{{ $project->sector->name() }}</span>
                @endif
            </p>
            <h1 class="heading-1 mt-3">{{ $translation->title }}</h1>
            @if ($translation->summary)
                <p class="mt-3 text-lg text-ink-muted">{{ $translation->summary }}</p>
            @endif
            @if ($project->progress !== null)
                <x-progress-bar :value="$project->progress" class="mt-6 max-w-md" />
            @endif
        </header>

        <div class="mt-10 grid gap-10 lg:grid-cols-3">
            <div class="lg:col-span-2">
                @if ($project->cover)
                    <x-picture :media="$project->cover" preset="landscape" sizes="(min-width: 64rem) 42rem, 100vw" loading="eager" fetchpriority="high" class="mb-8 w-full rounded-card" />
                @endif

                @if (trim($body) !== '')
                    <div class="prose-content max-w-prose">{!! $body !!}</div>
                @endif

                @if ($project->statistics->isNotEmpty())
                    <section aria-labelledby="project-numbers" class="mt-10">
                        <h2 id="project-numbers" class="heading-2">{{ __('projects.numbers') }}</h2>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            @foreach ($project->statistics as $statistic)
                                <x-statistic-tile :statistic="$statistic" />
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($photos->isNotEmpty() && $galleryTranslation)
                    <section aria-labelledby="project-photos" class="mt-10">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <h2 id="project-photos" class="heading-2">{{ __('projects.photos') }}</h2>
                            <a href="{{ route('gallery.show', ['locale' => $galleryTranslation->locale, 'slug' => $galleryTranslation->slug]) }}" class="text-sm text-link underline-offset-4 hover:underline">{{ __('projects.all_photos') }}</a>
                        </div>
                        <ul class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3">
                            @foreach ($photos as $item)
                                <li><x-picture :media="$item->media" preset="square" sizes="(min-width: 40rem) 14rem, 50vw" class="aspect-square w-full rounded object-cover" /></li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($project->hasMap())
                    <section aria-labelledby="project-map" class="mt-10">
                        <h2 id="project-map" class="heading-3">{{ __('offices.map') }}</h2>
                        <x-map-frame :latitude="$project->latitude" :longitude="$project->longitude" :title="__('offices.map_of', ['office' => $translation->title])" />
                    </section>
                @endif
            </div>

            <aside aria-label="{{ __('projects.details') }}">
                <dl class="space-y-3 text-sm">
                    @if (filled($translation->location) || $office)
                        <div>
                            <dt class="font-semibold">{{ __('projects.where') }}</dt>
                            <dd class="mt-0.5 text-ink-muted">
                                {{ $translation->location }}
                                @if ($office)
                                    <a href="{{ route('offices.show', ['locale' => $office->locale, 'slug' => $office->slug]) }}" class="block text-link hover:underline">{{ $office->getAttribute('name') }}</a>
                                @endif
                            </dd>
                        </div>
                    @endif
                    @if ($project->starts_on)
                        <div>
                            <dt class="font-semibold">{{ __('projects.starts_on') }}</dt>
                            <dd class="mt-0.5 text-ink-muted"><x-date :date="$project->starts_on" /></dd>
                        </div>
                    @endif
                    @if ($project->ends_on)
                        <div>
                            <dt class="font-semibold">{{ $project->status === \App\Domain\Projects\Enums\ProjectStatus::Completed ? __('projects.completed_on') : __('projects.ends_on') }}</dt>
                            <dd class="mt-0.5 text-ink-muted"><x-date :date="$project->ends_on" /></dd>
                        </div>
                    @endif
                    @if ($budget !== null)
                        <div>
                            <dt class="font-semibold">{{ __('projects.budget') }}</dt>
                            <dd class="mt-0.5 tabular-nums text-ink-muted" dir="ltr">{{ __('projects.birr', ['amount' => \Illuminate\Support\Number::format($budget, precision: 0, locale: 'en')]) }}</dd>
                        </div>
                    @endif
                </dl>
            </aside>
        </div>
    </article>
</x-layouts.public>
