<?php

namespace App\Console\Commands;

use App\Domain\Health\Backups\RestoreDrill;
use Illuminate\Console\Command;

/**
 * The restore drill (ADR-0037): the newest backup restored into the drill
 * database and checked. Monthly from the scheduler once DB_DRILL_DATABASE is
 * set; the result shows on the health page.
 */
final class RestoreDrillCommand extends Command
{
    protected $signature = 'portal:restore-drill {backup? : Backup path (default: the newest)}';

    protected $description = 'Restore a backup into the drill database and check it';

    public function handle(RestoreDrill $drill): int
    {
        if (blank(config('database.connections.drill.database'))) {
            $this->warn('No drill database: set DB_DRILL_DATABASE (and DB_DRILL_USERNAME, DB_DRILL_PASSWORD) first.');

            return self::FAILURE;
        }

        $result = $drill->run($this->argument('backup') ?: null);

        $this->line(sprintf('%s: %s — %d tables, %d migrations, %d s', $result['ok'] ? 'PASSED' : 'FAILED', $result['backup'], $result['tables'], $result['migrations'], $result['seconds']));
        foreach ($result['rows'] as $table => $count) {
            $this->line("  {$table}: {$count}");
        }
        if ($result['missing'] !== []) {
            $this->warn('Missing tables: '.implode(', ', $result['missing']));
        }
        if ($result['error'] !== null) {
            $this->error($result['error']);
        }

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
