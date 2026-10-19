<?php

namespace App\Filament\Resources\Media\Widgets;

use App\Domain\Media\Models\Media;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/**
 * Storage used against the quota, plus the two lists editors should work
 * through: images without alt text and files nobody uses (PLAN.md §7).
 */
class MediaStorageOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $files = Media::query()->library()->count();
        $bytes = (int) Media::query()->library()->sum('size');
        $quota = (float) config('portal.media.quota_gb', 10) * 1024 * 1024 * 1024;
        $percent = $quota > 0 ? round($bytes / $quota * 100, 1) : 0;

        return [
            Stat::make(__('admin.media.stats.files'), Number::format($files)),
            Stat::make(__('admin.media.stats.storage'), Number::fileSize($bytes, precision: 1))
                ->description(__('admin.media.stats.of_quota', [
                    'percent' => $percent,
                    'quota' => Number::fileSize((int) $quota),
                ]))
                ->color($percent >= 90 ? 'danger' : ($percent >= 75 ? 'warning' : 'success')),
            Stat::make(__('admin.media.stats.missing_alt'), Number::format(Media::query()->library()->missingAlt()->count()))
                ->color('warning'),
            Stat::make(__('admin.media.stats.unused'), Number::format(Media::query()->library()->unused()->count())),
        ];
    }
}
