<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Staff notices are kept 90 days after they were read and a year at most
 * (ADR-0041).
 */
final class PruneNotificationsCommand extends Command
{
    protected $signature = 'notifications:prune';

    protected $description = 'Delete old staff notifications';

    public function handle(): int
    {
        $deleted = DB::table('notifications')
            ->where(fn ($q) => $q->where('read_at', '<', now()->subDays(90))->orWhere('created_at', '<', now()->subYear()))
            ->delete();

        $this->components->info("Notifications deleted: {$deleted}");

        return self::SUCCESS;
    }
}
