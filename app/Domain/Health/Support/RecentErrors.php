<?php

namespace App\Domain\Health\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Str;
use Throwable;

/**
 * The newest errors in the application log (ADR-0038), for the health page
 * while no error tracker is set up. Reads only the end of the newest log
 * files and only the first line of each entry, without its context.
 */
final class RecentErrors
{
    private const PATTERN = '/^\[(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})[^\]]*\] [\w-]+\.(ERROR|CRITICAL|ALERT|EMERGENCY): (.*)$/';

    private const TAIL_BYTES = 512 * 1024;

    /**
     * @return list<array{at: CarbonImmutable, level: string, message: string}> newest first
     */
    public static function read(int $limit = 10, ?DateTimeInterface $since = null, ?string $directory = null): array
    {
        $files = glob(($directory ?? storage_path('logs')).'/laravel*.log') ?: [];
        usort($files, fn (string $a, string $b): int => (int) filemtime($b) <=> (int) filemtime($a));

        $entries = [];
        foreach (array_slice($files, 0, 3) as $file) {
            foreach (self::lines($file) as $line) {
                if (preg_match(self::PATTERN, $line, $m) !== 1) {
                    continue;
                }
                try {
                    $at = CarbonImmutable::parse($m[1], (string) config('app.timezone', 'UTC'));
                } catch (Throwable) {
                    continue;
                }
                if ($since !== null && $at->lessThan($since)) {
                    continue;
                }
                $entries[] = ['at' => $at, 'level' => $m[2], 'message' => Str::limit(trim(Str::before($m[3], ' {"')), 300)];
            }
        }

        usort($entries, fn (array $a, array $b): int => $b['at'] <=> $a['at']);

        return array_slice($entries, 0, $limit);
    }

    public static function countSince(DateTimeInterface $since, ?string $directory = null): int
    {
        return count(self::read(PHP_INT_MAX, $since, $directory));
    }

    /**
     * @return list<string>
     */
    private static function lines(string $file): array
    {
        $handle = @fopen($file, 'rb');
        if ($handle === false) {
            return [];
        }
        $size = (int) filesize($file);
        if ($size > self::TAIL_BYTES) {
            fseek($handle, $size - self::TAIL_BYTES);
            fgets($handle); // skip the partial first line
        }
        $text = (string) stream_get_contents($handle);
        fclose($handle);

        return preg_split('/\R/', $text) ?: [];
    }
}
