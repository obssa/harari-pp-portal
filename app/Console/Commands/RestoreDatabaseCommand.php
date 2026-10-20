<?php

namespace App\Console\Commands;

use App\Domain\Health\Backups\BackupArchive;
use App\Domain\Health\Backups\RestoreDatabase;
use Illuminate\Console\Command;

/**
 * The one-command restore (PLAN.md §11, ADR-0037, docs/runbook/restore.md):
 * loads the database of a backup, the newest by default, into the live
 * database or another connection. It asks before overwriting.
 */
final class RestoreDatabaseCommand extends Command
{
    protected $signature = 'portal:restore-database
        {backup? : Backup path on the backups disk, e.g. harari-pp-portal/2026-10-20-23-00-00.zip (default: the newest)}
        {--connection= : Database connection to restore into (default: the live one)}
        {--force : Do not ask}';

    protected $description = 'Restore the database from a backup';

    public function handle(RestoreDatabase $restore): int
    {
        $backup = (string) ($this->argument('backup') ?: BackupArchive::latest());
        if ($backup === '' || ! BackupArchive::exists($backup)) {
            $this->error('No such backup. Backups: '.implode(', ', array_column(BackupArchive::all(), 'path')));

            return self::FAILURE;
        }

        $connection = (string) ($this->option('connection') ?: config('database.default'));
        $database = (string) config("database.connections.{$connection}.database");
        if (! $this->option('force') && ! $this->confirm("Replace the tables of the database \"{$database}\" ({$connection}) with the backup {$backup}?")) {
            $this->line('Nothing changed.');

            return self::FAILURE;
        }

        $started = microtime(true);
        $restore->handle($backup, $connection);
        $this->info(sprintf('Restored %s into %s in %d s. Run "php artisan migrate" if the backup is older than the code.', $backup, $database, round(microtime(true) - $started)));

        activity('backups')->event('restore')->withProperties(['backup' => $backup, 'connection' => $connection])->log('Database restored from a backup');

        return self::SUCCESS;
    }
}
