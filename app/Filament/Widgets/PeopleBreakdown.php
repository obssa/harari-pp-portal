<?php

namespace App\Filament\Widgets;

use App\Domain\Dashboard\Contracts\DashboardWidget;
use App\Domain\Membership\Support\PeopleStats;
use App\Filament\Support\CurrentUser;
use App\Filament\Widgets\Concerns\IsDashboardWidget;
use Filament\Widgets\Widget;

/**
 * Where applications get to and who the members are (PLAN.md §7, ADR-0050):
 * the application funnel of the period, and active members by woreda, sex
 * and age band. Numbers only.
 */
class PeopleBreakdown extends Widget implements DashboardWidget
{
    use IsDashboardWidget;

    protected string $view = 'filament.widgets.people-breakdown';

    public static function key(): string
    {
        return 'people_breakdown';
    }

    public static function title(): string
    {
        return __('members.dashboard.breakdown');
    }

    public static function permission(): ?string
    {
        return 'members.view';
    }

    public static function defaultSize(): int
    {
        return 12;
    }

    /**
     * @return array{funnel: array{started: int, sent: int, approved: int, rejected: int}|null, woreda: list<array{key: string, label: string, count: int, share: float}>, sex: list<array{key: string, label: string, count: int, share: float}>, age_band: list<array{key: string, label: string, count: int, share: float}>}
     */
    public function figures(): array
    {
        $filters = $this->filters();
        $user = CurrentUser::get();
        $stats = new PeopleStats($user, $filters->officeId);

        return [
            'funnel' => $user->can('applications.view') ? $stats->funnel($filters->from, $filters->to) : null,
            'woreda' => array_slice($stats->breakdown('woreda'), 0, 8),
            'sex' => $stats->breakdown('sex'),
            'age_band' => $stats->breakdown('age_band'),
        ];
    }
}
