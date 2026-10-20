<?php

namespace App\Domain\Health\Backups;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Loads the database dump of a backup into a MySQL or MariaDB connection with
 * the `mysql` client (ADR-0037). The dump drops and recreates every table it
 * holds; tables it does not hold are left alone. Credentials go through a
 * private option file, never the command line.
 */
final class RestoreDatabase
{
    public function handle(string $backup, string $connection): void
    {
        /** @var array<string, mixed>|null $config */
        $config = config("database.connections.{$connection}");
        if (! is_array($config) || ! in_array($config['driver'] ?? null, ['mysql', 'mariadb'], true) || blank($config['database'] ?? null)) {
            throw new InvalidArgumentException("The connection {$connection} is not a MySQL database with a name.");
        }

        $dump = BackupArchive::extractDump($backup);
        // Inside the private (0700) folder of the unpacked dump, removed with it.
        $options = "{$dump['dir']}/client.cnf";

        try {
            touch($options);
            chmod($options, 0600);
            file_put_contents($options, $this->optionFile($config));
            $input = fopen((str_ends_with($dump['file'], '.gz') ? 'compress.zlib://' : '').$dump['file'], 'rb');
            if ($input === false) {
                throw new RuntimeException('The database dump cannot be read.');
            }

            $process = new Process([
                (string) config('portal.mysql_client', 'mysql'),
                "--defaults-extra-file={$options}",
                '--default-character-set=utf8mb4',
                (string) $config['database'],
            ]);
            $process->setInput($input)->setTimeout(3600)->mustRun();
            fclose($input);
        } finally {
            File::deleteDirectory($dump['dir']);
        }

        DB::purge($connection);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function optionFile(array $config): string
    {
        $quote = fn (mixed $value): string => '"'.addcslashes((string) $value, '\\"').'"';
        $lines = ['[client]', 'user='.$quote($config['username'] ?? ''), 'password='.$quote($config['password'] ?? '')];
        if (filled($config['unix_socket'] ?? null)) {
            $lines[] = 'socket='.$quote($config['unix_socket']);
        } else {
            $lines[] = 'host='.$quote($config['host'] ?? '127.0.0.1');
            $lines[] = 'port='.(int) ($config['port'] ?? 3306);
        }

        return implode("\n", $lines)."\n";
    }
}
