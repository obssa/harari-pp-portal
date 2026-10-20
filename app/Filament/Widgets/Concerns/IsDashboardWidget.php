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

    /**
     * The width chosen in the dashboard layout, in columns of 12 (ADR-0021).
     * Public, so the layout can set it: Filament's own column span is
     * protected and cannot be passed in.
     *
     * @var array<string, int>|null
     */
    public ?array $span = null;

    /**
     * @return int|string|array<string, int|string|null>
     */
    public function getColumnSpan(): int|string|array
    {
        return $this->span ?? $this->columnSpan;
    }

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
