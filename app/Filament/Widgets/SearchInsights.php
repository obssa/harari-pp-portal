<?php

namespace App\Filament\Widgets;

use App\Domain\Dashboard\Contracts\DashboardWidget;
use App\Domain\Search\Models\SearchTerm;
use App\Filament\Widgets\Concerns\IsDashboardWidget;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Search insights (PLAN.md §7): the most searched words in the range and
 * searches that found nothing, the gaps in the content.
 */
class SearchInsights extends Widget implements DashboardWidget
{
    use IsDashboardWidget;

    protected string $view = 'filament.widgets.search-insights';

    public static function key(): string
    {
        return 'search';
    }

    public static function title(): string
    {
        return __('dashboard.widget.search');
    }

    public static function permission(): ?string
    {
        return 'analytics.view';
    }

    public static function defaultSize(): int
    {
        return 6;
    }

    /**
     * @return array{top: Collection<int, SearchTerm>, empty: Collection<int, SearchTerm>}
     */
    public function insights(): array
    {
        $filters = $this->filters();
        $base = fn (): Builder => SearchTerm::query()
            ->where('last_searched_at', '>=', $filters->from)
            ->when($filters->locale, fn (Builder $q, string $l) => $q->where('locale', $l));

        return [
            'top' => $base()->orderByDesc('searches')->limit(5)->get(),
            'empty' => $base()->where('last_results', 0)->orderByDesc('searches')->limit(5)->get(),
        ];
    }
}
