<?php

namespace App\Filament\Widgets;

use App\Domain\Dashboard\Contracts\DashboardWidget;
use App\Domain\Dashboard\Models\Announcement;
use App\Filament\Widgets\Concerns\IsDashboardWidget;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Current notices from Super Admins to staff (PLAN.md §7 announcements).
 * Shows nothing when there are none.
 */
class Announcements extends Widget implements DashboardWidget
{
    use IsDashboardWidget;

    protected string $view = 'filament.widgets.announcements';

    /** Notices are cheap to show and should be seen at once. */
    protected static bool $isLazy = false;

    public static function key(): string
    {
        return 'announcements';
    }

    public static function title(): string
    {
        return __('dashboard.widget.announcements');
    }

    public static function permission(): ?string
    {
        return null;
    }

    public static function defaultSize(): int
    {
        return 12;
    }

    /**
     * @return Collection<int, Announcement>
     */
    public function announcements(): Collection
    {
        return Announcement::query()->current()->latest('id')->limit(3)->get();
    }
}
