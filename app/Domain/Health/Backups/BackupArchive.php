<?php

namespace App\Domain\Health\Backups;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * The backups on the server disk (ADR-0037): one encrypted zip per run with
 * the database dump under `db-dumps/` and the uploaded files.
 */
final class BackupArchive
{
    public const DISK = 'backups';

    /**
     * @return list<array{path: string, name: string, size: int, date: CarbonImmutable}> newest first
     */
    public static function all(): array
    {
        $disk = Storage::disk(self::DISK);

        return collect($disk->files((string) config('backup.backup.name')))
            ->filter(fn (string $path): bool => str_ends_with($path, '.zip'))
            ->map(fn (string $path): array => [
                'path' => $path,
                'name' => basename($path),
                'size' => (int) $disk->size($path),
                'date' => CarbonImmutable::createFromTimestamp((int) $disk->lastModified($path)),
            ])
            ->sortBy([fn (array $a, array $b): int => $b['date'] <=> $a['date'], fn (array $a, array $b): int => strcmp($b['path'], $a['path'])])
            ->values()
            ->all();
    }

    public static function latest(): ?string
    {
        return self::all()[0]['path'] ?? null;
    }

    public static function exists(string $path): bool
    {
        return str_ends_with($path, '.zip') && ! str_contains($path, '..')
            && collect(self::all())->contains(fn (array $backup): bool => $backup['path'] === $path);
    }

    /**
     * Unpacks the database dump of a backup into a new temporary folder.
     *
     * @return array{dir: string, file: string} delete `dir` when done
     */
    public static function extractDump(string $path): array
    {
        if (! self::exists($path)) {
            throw new RuntimeException("No backup called {$path}.");
        }

        $zip = new ZipArchive;
        if ($zip->open(Storage::disk(self::DISK)->path($path)) !== true) {
            throw new RuntimeException("The backup {$path} cannot be opened.");
        }

        try {
            $password = config('backup.backup.password');
            if (is_string($password) && $password !== '') {
                $zip->setPassword($password);
            }

            $entry = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if (str_starts_with($name, 'db-dumps/') && (str_ends_with($name, '.sql.gz') || str_ends_with($name, '.sql'))) {
                    $entry = $name;
                    break;
                }
            }
            if ($entry === null) {
                throw new RuntimeException('The backup holds no database dump.');
            }

            $dir = storage_path('app/backup-temp/restore-'.Str::random(12));
            File::ensureDirectoryExists($dir, 0700);
            if (! $zip->extractTo($dir, $entry) || ! is_file("{$dir}/{$entry}") || filesize("{$dir}/{$entry}") === 0) {
                File::deleteDirectory($dir);

                throw new RuntimeException('The database dump could not be unpacked: is BACKUP_ARCHIVE_PASSWORD the one the backup was made with?');
            }

            return ['dir' => $dir, 'file' => "{$dir}/{$entry}"];
        } finally {
            $zip->close();
        }
    }
}
