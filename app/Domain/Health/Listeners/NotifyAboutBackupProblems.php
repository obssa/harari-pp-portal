<?php

namespace App\Domain\Health\Listeners;

use App\Domain\Health\Notifications\HealthFailingNotice;
use App\Domain\Notifications\Support\Recipients;
use Illuminate\Events\Dispatcher;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\CleanupHasFailed;
use Spatie\Backup\Events\UnhealthyBackupWasFound;

/**
 * A backup, its clean-up or the daily backup monitor went wrong (ADR-0037):
 * administrators get a portal notice instead of the package's own mail.
 */
final class NotifyAboutBackupProblems
{
    public function failed(BackupHasFailed $event): void
    {
        $this->notify('Backup failed: '.$event->exception->getMessage());
    }

    public function cleanupFailed(CleanupHasFailed $event): void
    {
        $this->notify('Removing old backups failed: '.$event->exception->getMessage());
    }

    public function unhealthy(UnhealthyBackupWasFound $event): void
    {
        $this->notify('Backups need attention: '.$event->failureMessages->pluck('message')->implode(' '));
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(BackupHasFailed::class, [self::class, 'failed']);
        $events->listen(CleanupHasFailed::class, [self::class, 'cleanupFailed']);
        $events->listen(UnhealthyBackupWasFound::class, [self::class, 'unhealthy']);
    }

    private function notify(string $message): void
    {
        Recipients::notify(Recipients::withPermission('settings.view'), new HealthFailingNotice('backups', $message));
    }
}
