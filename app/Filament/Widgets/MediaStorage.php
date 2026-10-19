<?php

namespace App\Filament\Widgets;

use App\Domain\Dashboard\Contracts\DashboardWidget;
use App\Filament\Resources\Media\Widgets\MediaStorageOverview;
use App\Filament\Widgets\Concerns\IsDashboardWidget;

/**
 * Media storage against the quota (PLAN.md §7), the same figures as on the
 * media library screen.
 */
class MediaStorage extends MediaStorageOverview implements DashboardWidget
{
    use IsDashboardWidget;

    public static function key(): string
    {
        return 'media';
    }

    public static function title(): string
    {
        return __('dashboard.widget.media');
    }

    public static function permission(): ?string
    {
        return 'media.view';
    }

    public static function defaultSize(): int
    {
        return 12;
    }
}
