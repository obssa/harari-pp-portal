<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Logs a request or job that spends longer than the limit in the database
 * (ADR-0061), once per request, with where it happened and the query that
 * crossed the limit, so slow pages show up in the log and in Sentry.
 */
final class SlowQueries
{
    public static function watch(int $milliseconds): void
    {
        if ($milliseconds <= 0) {
            return;
        }

        DB::whenQueryingForLongerThan($milliseconds, function (Connection $connection, QueryExecuted $event): void {
            Log::warning('Slow database work', [
                'total_ms' => (int) $connection->totalQueryDuration(),
                'where' => app()->runningInConsole() ? implode(' ', array_slice((array) ($_SERVER['argv'] ?? []), 0, 3)) : request()->method().' '.request()->path(),
                'query_ms' => (int) $event->time,
                'query' => mb_substr($event->sql, 0, 500),
            ]);
        });
    }
}
