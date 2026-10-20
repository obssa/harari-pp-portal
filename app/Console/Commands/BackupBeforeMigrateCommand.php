<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;

/**
 * Run by the deploy before `migrate` (PLAN.md §11: migrations run after a
 * backup, ADR-0037): when migrations are waiting, the database is backed up
 * first, and a failed backup stops the release before anything changes.
 */
final class BackupBeforeMigrateCommand extends Command
{
    protected $signature = 'portal:backup-before-migrate';

    protected $description = 'Back up the database when migrations are pending';

    public function handle(Migrator $migrator): int
    {
        if (! $migrator->repositoryExists()) {
            $this->line('No migration history yet: nothing to back up.');

            return self::SUCCESS;
        }

        $files = $migrator->getMigrationFiles([database_path('migrations'), ...$migrator->paths()]);
        $pending = array_diff(array_keys($files), $migrator->getRepository()->getRan());
        if ($pending === []) {
            $this->line('No pending migrations: no backup needed.');

            return self::SUCCESS;
        }

        $this->line(count($pending).' pending migration(s): backing up the database first.');

        return $this->call('backup:run', [
            '--only-db' => true,
            '--disable-notifications' => true,
            '--filename' => 'before-migrate-'.now()->format('Y-m-d-H-i-s').'.zip',
        ]);
    }
}
