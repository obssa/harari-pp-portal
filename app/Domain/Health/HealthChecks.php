<?php

namespace App\Domain\Health;

use App\Domain\Health\Backups\BackupArchive;
use App\Domain\Health\Checks\ErrorLogCheck;
use App\Domain\Health\Checks\FailedJobsCheck;
use App\Domain\Health\Checks\MailCheck;
use App\Domain\Health\Checks\QueueBacklogCheck;
use App\Domain\Health\Checks\StorageSizeCheck;
use Spatie\Health\Checks\Checks\BackupsCheck;
use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\OptimizedAppCheck;
use Spatie\Health\Checks\Checks\QueueCheck;
use Spatie\Health\Checks\Checks\ScheduleCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\Health\Facades\Health;

/**
 * The portal's health checks (PLAN.md §11 reliability, ADR-0037). They run
 * every five minutes from the scheduler; administrators see the latest
 * results under Administration → System health. `/up` stays the plain
 * "the application boots" check for uptime monitors.
 */
final class HealthChecks
{
    /** Check names, in the order the health page shows them. */
    public const NAMES = [
        'database', 'cache', 'scheduler', 'queue', 'queue_backlog', 'failed_jobs',
        'mail', 'storage', 'disk', 'backups', 'errors', 'debug_mode', 'optimized',
    ];

    public static function register(): void
    {
        /** @var list<string> $queues */
        $queues = (array) config('portal.queues', ['default']);
        $quota = config('portal.storage_quota_mb');

        Health::checks([
            DatabaseCheck::new()->name('database'),
            CacheCheck::new()->name('cache'),
            ScheduleCheck::new()->name('scheduler')->heartbeatMaxAgeInMinutes(5),
            QueueCheck::new()->name('queue')->onQueue($queues)->failWhenHealthJobTakesLongerThanMinutes(10),
            QueueBacklogCheck::new()->name('queue_backlog'),
            FailedJobsCheck::new()->name('failed_jobs'),
            MailCheck::new()->name('mail'),
            StorageSizeCheck::new()->name('storage')
                ->paths([storage_path('app/public'), storage_path('app/media'), storage_path('app/private'), storage_path('backups')])
                ->quotaMb(is_numeric($quota) ? (int) $quota : null),
            UsedDiskSpaceCheck::new()->name('disk')->warnWhenUsedSpaceIsAbovePercentage(85)->failWhenUsedSpaceIsAbovePercentage(95),
            // The nightly backup runs at 02:00 Addis Ababa time; a day and two hours gives it room.
            BackupsCheck::new()->name('backups')->onDisk(BackupArchive::DISK)->locatedAt((string) config('backup.backup.name'))
                ->youngestBackShouldHaveBeenMadeBefore(now()->subHours(26)),
            ErrorLogCheck::new()->name('errors'),
            DebugModeCheck::new()->name('debug_mode'),
            OptimizedAppCheck::new()->name('optimized')->unless(app()->environment('local', 'testing')),
        ]);
    }

    public static function label(string $name): string
    {
        return match ($name) {
            'database' => __('health.check.database'),
            'cache' => __('health.check.cache'),
            'scheduler' => __('health.check.scheduler'),
            'queue' => __('health.check.queue'),
            'queue_backlog' => __('health.check.queue_backlog'),
            'failed_jobs' => __('health.check.failed_jobs'),
            'mail' => __('health.check.mail'),
            'storage' => __('health.check.storage'),
            'disk' => __('health.check.disk'),
            'debug_mode' => __('health.check.debug_mode'),
            'optimized' => __('health.check.optimized'),
            'backups' => __('health.check.backups'),
            'errors' => __('health.check.errors'),
            default => $name,
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'ok' => __('health.status.ok'),
            'warning' => __('health.status.warning'),
            'failed' => __('health.status.failed'),
            'crashed' => __('health.status.crashed'),
            'skipped' => __('health.status.skipped'),
            default => $status,
        };
    }

    public static function color(string $status): string
    {
        return match ($status) {
            'ok' => 'success',
            'warning' => 'warning',
            'failed', 'crashed' => 'danger',
            default => 'gray',
        };
    }
}
