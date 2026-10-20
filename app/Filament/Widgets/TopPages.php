<?php

namespace App\Filament\Widgets;

use App\Domain\Analytics\Support\TrafficReport;
use App\Domain\Dashboard\Contracts\DashboardWidget;
use App\Filament\Widgets\Concerns\IsDashboardWidget;
use Filament\Widgets\Widget;

/**
 * The most read pages in the dashboard's range (PLAN.md §7, ADR-0040).
 */
class TopPages extends Widget implements DashboardWidget
{
    use IsDashboardWidget;

    protected string $view = 'filament.widgets.top-pages';

    public static function key(): string
    {
        return 'top_pages';
    }

    public static function title(): string
    {
        return __('dashboard.widget.top_pages');
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
     * @return list<array{key: string, label: string, locale: ?string, views: int, share: float}>
     */
    public function pages(): array
    {
        $filters = $this->filters();

        return (new TrafficReport($filters->from, $filters->to, $filters->locale))->rows('pages', 10);
    }
}
