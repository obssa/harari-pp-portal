<?php

namespace App\Filament\Widgets;

use App\Domain\Dashboard\Contracts\DashboardWidget;
use App\Domain\Membership\Support\PeopleStats;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\MembershipApplications\MembershipApplicationResource;
use App\Filament\Resources\Volunteers\VolunteerResource;
use App\Filament\Support\CurrentUser;
use App\Filament\Widgets\Concerns\IsDashboardWidget;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/**
 * People at a glance (PLAN.md §7, ADR-0050): active members, members who
 * joined in the period against the one before, applications waiting and
 * volunteering, for the woredas the viewer looks after.
 */
class PeopleOverview extends StatsOverviewWidget implements DashboardWidget
{
    use IsDashboardWidget;

    protected ?string $pollingInterval = null;

    public static function key(): string
    {
        return 'people';
    }

    public static function title(): string
    {
        return __('members.dashboard.overview');
    }

    public static function permission(): ?string
    {
        return 'members.view';
    }

    public static function defaultSize(): int
    {
        return 12;
    }

    protected function getStats(): array
    {
        $filters = $this->filters();
        $user = CurrentUser::get();
        $stats = new PeopleStats($user, $filters->officeId);
        $joined = $stats->joined($filters->from, $filters->to);
        $before = $stats->joined($filters->previousFrom(), $filters->from->subSecond());
        $delta = $joined - $before;

        $cards = [
            Stat::make(__('members.dashboard.active'), Number::format($stats->activeMembers()))
                ->description(__('members.dashboard.active_help'))
                ->url(MemberResource::getUrl()),
            Stat::make(__('members.dashboard.joined'), Number::format($joined))
                ->description(__('dashboard.kpi.versus_previous', ['delta' => ($delta >= 0 ? '+' : '').$delta]))
                ->descriptionIcon($delta >= 0 ? Heroicon::ArrowTrendingUp : Heroicon::ArrowTrendingDown)
                ->color($delta >= 0 ? 'success' : 'warning'),
        ];
        if ($user->can('applications.view')) {
            $waiting = $stats->waitingApplications();
            $cards[] = Stat::make(__('members.dashboard.waiting'), Number::format($waiting))
                ->description(__('members.dashboard.waiting_help'))
                ->color($waiting > 0 ? 'warning' : 'gray')
                ->url(MembershipApplicationResource::getUrl());
        }
        if ($user->can('volunteers.view')) {
            $volunteering = $stats->volunteering($filters->from, $filters->to);
            $cards[] = Stat::make(__('members.dashboard.volunteers'), Number::format($volunteering['active']))
                ->description(__('members.dashboard.volunteers_help', ['new' => $volunteering['new'], 'hours' => Number::format($volunteering['hours'], maxPrecision: 1)]))
                ->url(VolunteerResource::getUrl());
        }

        return $cards;
    }
}
