<?php

namespace App\Filament\Widgets;

use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostTranslation;
use App\Domain\Dashboard\Contracts\DashboardWidget;
use App\Domain\Dashboard\Support\DashboardFilters;
use App\Domain\Search\Models\SearchTerm;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\SearchTerms\SearchTermResource;
use App\Filament\Widgets\Concerns\IsDashboardWidget;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

/**
 * Key numbers for the chosen range, language and office (PLAN.md §7 KPI
 * strip): published, waiting for review, missing translations and searches
 * that found nothing, with the change against the previous period and a
 * daily trend.
 */
class KpiStrip extends StatsOverviewWidget implements DashboardWidget
{
    use IsDashboardWidget;

    protected ?string $pollingInterval = null;

    public static function key(): string
    {
        return 'kpis';
    }

    public static function title(): string
    {
        return __('dashboard.widget.kpis');
    }

    public static function permission(): ?string
    {
        return 'posts.view';
    }

    public static function defaultSize(): int
    {
        return 12;
    }

    protected function getStats(): array
    {
        $filters = $this->filters();

        return array_filter([
            $this->published($filters),
            $this->inReview($filters),
            $this->missingTranslations($filters),
            $this->searchesWithoutResults($filters),
        ]);
    }

    private function published(DashboardFilters $filters): Stat
    {
        $dates = $this->translations($filters)
            ->where('status', ContentStatus::Published->value)
            ->whereBetween('published_at', [$filters->previousFrom(), $filters->to])
            ->pluck('published_at')
            ->map(fn (mixed $date): CarbonImmutable => CarbonImmutable::parse($date));

        $current = $dates->filter(fn (CarbonImmutable $d): bool => $d->greaterThanOrEqualTo($filters->from))->count();
        $previous = $dates->count() - $current;
        $delta = $current - $previous;

        return Stat::make(__('dashboard.kpi.published'), Number::format($current))
            ->description(__('dashboard.kpi.versus_previous', ['delta' => ($delta >= 0 ? '+' : '').$delta]))
            ->descriptionIcon($delta >= 0 ? Heroicon::ArrowTrendingUp : Heroicon::ArrowTrendingDown)
            ->color($delta >= 0 ? 'success' : 'warning')
            ->chart($this->trend($dates->filter(fn (CarbonImmutable $d): bool => $d->greaterThanOrEqualTo($filters->from))->all(), $filters))
            ->url(PostResource::getUrl());
    }

    private function inReview(DashboardFilters $filters): Stat
    {
        $count = $this->translations($filters)->where('status', ContentStatus::InReview->value)->count();

        return Stat::make(__('dashboard.kpi.in_review'), Number::format($count))
            ->description(__('dashboard.kpi.in_review_help'))
            ->color($count > 0 ? 'warning' : 'gray')
            ->url(PostResource::getUrl());
    }

    private function missingTranslations(DashboardFilters $filters): Stat
    {
        $languages = $filters->locale !== null ? [$filters->locale] : ['am', 'om'];

        $count = $this->posts($filters)
            ->whereHas('translations', fn (Builder $q) => $q->where('status', ContentStatus::Published->value))
            ->where(function (Builder $q) use ($languages): void {
                foreach ($languages as $language) {
                    $q->orWhereDoesntHave('translations', fn (Builder $t) => $t
                        ->where('locale', $language)
                        ->where('status', ContentStatus::Published->value));
                }
            })
            ->count();

        return Stat::make(__('dashboard.kpi.missing_translations'), Number::format($count))
            ->description(__('dashboard.kpi.missing_translations_help', ['languages' => strtoupper(implode(', ', $languages))]))
            ->color($count > 0 ? 'warning' : 'success')
            ->url(PostResource::getUrl());
    }

    private function searchesWithoutResults(DashboardFilters $filters): ?Stat
    {
        if (! (auth()->user()?->can('analytics.view') ?? false)) {
            return null;
        }

        $count = SearchTerm::query()
            ->where('last_results', 0)
            ->where('last_searched_at', '>=', $filters->from)
            ->when($filters->locale, fn (Builder $q, string $l) => $q->where('locale', $l))
            ->count();

        return Stat::make(__('dashboard.kpi.searches_without_results'), Number::format($count))
            ->description(__('dashboard.kpi.searches_without_results_help'))
            ->color($count > 0 ? 'warning' : 'gray')
            ->url(SearchTermResource::getUrl());
    }

    /**
     * @return Builder<PostTranslation>
     */
    private function translations(DashboardFilters $filters): Builder
    {
        return PostTranslation::query()
            ->when($filters->locale, fn (Builder $q, string $l) => $q->where('locale', $l))
            ->whereHas('post', fn (Builder $q) => $q->when($filters->officeId, fn (Builder $p, string $id) => $p->whereHas('offices', fn (Builder $o) => $o->whereKey($id))));
    }

    /**
     * @return Builder<Post>
     */
    private function posts(DashboardFilters $filters): Builder
    {
        return Post::query()->when($filters->officeId, fn (Builder $p, string $id) => $p->whereHas('offices', fn (Builder $o) => $o->whereKey($id)));
    }

    /**
     * Counts per day (per week for ranges over 60 days).
     *
     * @param  array<int, CarbonImmutable>  $dates
     * @return list<float>
     */
    private function trend(array $dates, DashboardFilters $filters): array
    {
        $days = max(1, (int) ceil($filters->from->diffInDays($filters->to)));
        $bucket = $days > 60 ? 7 : 1;
        $buckets = array_fill(0, (int) ceil($days / $bucket), 0.0);
        foreach ($dates as $date) {
            $index = min(count($buckets) - 1, (int) floor($filters->from->diffInDays($date) / $bucket));
            $buckets[$index]++;
        }

        return $buckets;
    }
}
