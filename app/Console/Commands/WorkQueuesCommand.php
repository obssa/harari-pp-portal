<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Queue\Worker;

/**
 * Drains every portal queue once, then exits (ADR-0002: no resident worker
 * on shared hosting; cron starts this every minute). The queue list lives in
 * config/portal.php, so new queues never need a server change.
 */
final class WorkQueuesCommand extends Command
{
    protected $signature = 'portal:work-queues
        {--max-time=50 : Stop after this many seconds so runs never overlap}
        {--memory=256 : Stop after a job once the process uses this many megabytes}
        {--tries=3 : Attempts per job before it is marked failed}';

    protected $description = 'Process all portal queues (default, media, ...) until empty';

    public function handle(): int
    {
        /** @var list<string> $queues */
        $queues = (array) config('portal.queues', ['default']);

        $code = $this->call('queue:work', [
            '--queue' => implode(',', $queues),
            '--stop-when-empty' => true,
            '--max-time' => (int) $this->option('max-time'),
            '--memory' => (int) $this->option('memory'),
            '--tries' => (int) $this->option('tries'),
        ]);

        // Reaching the memory limit is a normal stop for a cron-run worker:
        // the remaining jobs are picked up by the next run.
        if ($code === Worker::EXIT_MEMORY_LIMIT) {
            $this->components->info('Memory limit reached; the next run continues with the remaining jobs.');

            return self::SUCCESS;
        }

        return $code;
    }
}
