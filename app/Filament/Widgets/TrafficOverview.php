<?php

namespace App\Filament\Widgets;

use App\Domain\Analytics\Support\TrafficReport;
use App\Domain\Dashboard\Contracts\DashboardWidget;
use App\Filament\Widgets\Concerns\IsDashboardWidget;
use Filament\Widgets\Widget;

/**
 * Visits to the public site (PLAN.md §7 traffic, ADR-0040): page views in
 * the dashboard's range against the range before, a day-by-day bar, and the
 * share of each language and kind of device. Counted without cookies.
 */
class TrafficOverview extends Widget implements DashboardWidget
{
    use IsDashboardWidget;

    protected string $view = 'filament.widgets.traffic-overview';

    public static function key(): string
    {
        return 'traffic';
    }

    public static function title(): string
    {
        return __('dashboard.widget.traffic');
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
     * @return array{total: int, previous: int, change: ?float, daily: array<string, int>, languages: list<array{key: string, label: string, locale: ?string, views: int, share: float}>, devices: list<array{key: string, label: string, locale: ?string, views: int, share: float}>}
     */
    public function traffic(): array
    {
        $filters = $this->filters();
        $report = new TrafficReport($filters->from, $filters->to, $filters->locale);
        $total = $report->total();
        $previous = (new TrafficReport($filters->previousFrom(), $filters->from->subSecond(), $filters->locale))->total();

        return [
            'total' => $total,
            'previous' => $previous,
            'change' => $previous > 0 ? round(($total - $previous) / $previous * 100) : null,
            'daily' => $report->daily(),
            'languages' => $report->rows('languages', 4),
            'devices' => $report->rows('devices', 4),
        ];
    }
}
