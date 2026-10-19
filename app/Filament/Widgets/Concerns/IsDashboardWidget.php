<?php

namespace App\Filament\Widgets\Concerns;

use App\Domain\Dashboard\Support\DashboardFilters;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Shared behaviour of dashboard widgets (ADR-0021): shown only to staff
 * with the widget's permission, and reading the dashboard's filters.
 */
trait IsDashboardWidget
{
    use InteractsWithPageFilters;

    public static function canView(): bool
    {
        $user = Filament::auth()->user();
        $permission = static::permission();

        return $user instanceof User && ($permission === null || $user->can($permission));
    }

    protected function filters(): DashboardFilters
    {
        return DashboardFilters::from($this->pageFilters);
    }
}
