<?php

namespace App\Domain\Health\Backups;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * The restore drill (PLAN.md §11, ADR-0037): restores a backup, the newest by
 * default, into the separate drill database, checks that the tables and the
 * migration history came back, counts the rows of the main tables, and
 * empties the drill database again so personal data is not kept twice.
 * Every drill is written to `restore-drills.json` on the backups disk.
 */
final class RestoreDrill
{
    public const LOG = 'restore-drills.json';

    private const COUNTED = ['users', 'posts', 'pages', 'events', 'documents', 'form_submissions', 'media', 'ui_translations'];

    public function __construct(private readonly RestoreDatabase $restore) {}

    /**
     * @return array{at: string, backup: string, ok: bool, seconds: int, tables: int, missing: list<string>, migrations: int, rows: array<string, int>, error: ?string}
     */
    public function run(?string $backup = null): array
    {
        $backup ??= BackupArchive::latest() ?? throw new RuntimeException('There is no backup to restore.');
        $live = (string) config('database.default');
        $liveName = (string) config("database.connections.{$live}.database");
        $drillName = (string) config('database.connections.drill.database');
        if ($drillName === '') {
            throw new RuntimeException('The drill database is not set (DB_DRILL_DATABASE).');
        }
        if ($drillName === $liveName) {
            throw new RuntimeException('The drill database must not be the live database.');
        }

        $started = microtime(true);
        $result = ['at' => CarbonImmutable::now()->toIso8601String(), 'backup' => $backup, 'ok' => false, 'seconds' => 0,
            'tables' => 0, 'missing' => [], 'migrations' => 0, 'rows' => [], 'error' => null];

        try {
            Schema::connection('drill')->dropAllTables();
            $this->restore->handle($backup, 'drill');

            $restored = Schema::connection('drill')->getTableListing($drillName, false);
            $missing = array_values(array_diff(Schema::connection($live)->getTableListing($liveName, false), $restored));
            $migrations = in_array('migrations', $restored, true) ? DB::connection('drill')->table('migrations')->count() : 0;
            $rows = [];
            foreach (self::COUNTED as $table) {
                if (in_array($table, $restored, true)) {
                    $rows[$table] = DB::connection('drill')->table($table)->count();
                }
            }

            // Tables missing from an older backup are fine when migrations ran after it.
            $newerMigrations = DB::connection($live)->table('migrations')->count() > $migrations;
            $result = [...$result, 'tables' => count($restored), 'missing' => $missing, 'migrations' => $migrations, 'rows' => $rows,
                'ok' => $restored !== [] && $migrations > 0 && ($missing === [] || $newerMigrations)];
        } catch (Throwable $e) {
            $result['error'] = mb_substr($e->getMessage(), 0, 500);
        } finally {
            try {
                Schema::connection('drill')->dropAllTables();
            } catch (Throwable) {
                // The drill database is emptied before the next drill as well.
            }
            DB::purge('drill');
        }

        $result['seconds'] = (int) round(microtime(true) - $started);
        $this->record($result);

        return $result;
    }

    /**
     * @return list<array<string, mixed>> newest first
     */
    public static function history(): array
    {
        $disk = Storage::disk(BackupArchive::DISK);
        $log = $disk->exists(self::LOG) ? json_decode((string) $disk->get(self::LOG), true) : [];

        return is_array($log) ? array_values(array_filter($log, 'is_array')) : [];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function record(array $result): void
    {
        $history = array_slice([$result, ...self::history()], 0, 24);
        Storage::disk(BackupArchive::DISK)->put(self::LOG, (string) json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        activity('backups')->event('restore_drill')->withProperties($result)
            ->log($result['ok'] ? 'Restore drill passed' : 'Restore drill failed');
    }
}
