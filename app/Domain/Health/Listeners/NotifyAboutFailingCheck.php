<?php

namespace App\Domain\Health\Listeners;

use App\Domain\Health\Notifications\HealthFailingNotice;
use App\Domain\Notifications\Support\Recipients;
use Spatie\Health\Events\CheckEndedEvent;
use Spatie\Health\Models\HealthCheckResultHistoryItem;

/**
 * Tells administrators when a check turns from passing to failing
 * (ADR-0037). The results of a run are stored after all checks ended, so the
 * newest stored result is the previous run's.
 */
final class NotifyAboutFailingCheck
{
    private const FAILING = ['failed', 'crashed'];

    public function handle(CheckEndedEvent $event): void
    {
        if (! in_array((string) $event->result->status->value, self::FAILING, true)) {
            return;
        }

        $previous = HealthCheckResultHistoryItem::query()
            ->where('check_name', $event->check->getName())
            ->latest('id')
            ->value('status');
        if (in_array($previous, self::FAILING, true)) {
            return;
        }

        Recipients::notify(
            Recipients::withPermission('settings.view'),
            new HealthFailingNotice($event->check->getName(), $event->result->getNotificationMessage()),
        );
    }
}
