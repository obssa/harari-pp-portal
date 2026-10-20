<?php

namespace App\Domain\Health\Checks;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * How long the oldest job has been waiting (ADR-0002, ADR-0037). On the
 * shared host cron drains the queues every minute, so a job waiting long
 * means the cron line or the worker stopped.
 */
final class QueueBacklogCheck extends Check
{
    private int $warnAfterMinutes = 15;

    private int $failAfterMinutes = 60;

    public function warnAfterMinutes(int $minutes): self
    {
        $this->warnAfterMinutes = $minutes;

        return $this;
    }

    public function failAfterMinutes(int $minutes): self
    {
        $this->failAfterMinutes = $minutes;

        return $this;
    }

    public function run(): Result
    {
        $now = CarbonImmutable::now()->getTimestamp();
        $waiting = DB::table('jobs')->whereNull('reserved_at')->where('available_at', '<=', $now);
        $count = (clone $waiting)->count();
        $oldest = (clone $waiting)->min('available_at');
        $minutes = $oldest === null ? 0 : intdiv(max(0, $now - (int) $oldest), 60);

        $result = Result::make()->meta(['waiting' => $count, 'oldest_minutes' => $minutes])->shortSummary("{$count} / {$minutes} min");

        return match (true) {
            $minutes >= $this->failAfterMinutes => $result->failed("The oldest of {$count} waiting jobs has waited {$minutes} minutes."),
            $minutes >= $this->warnAfterMinutes => $result->warning("The oldest of {$count} waiting jobs has waited {$minutes} minutes."),
            default => $result->ok(),
        };
    }
}
