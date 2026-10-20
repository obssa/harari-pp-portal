<?php

namespace App\Domain\Health\Checks;

use Illuminate\Support\Facades\DB;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Background jobs that gave up (ADR-0037): a few call for a look, many for
 * action. They stay listed until retried or removed.
 */
final class FailedJobsCheck extends Check
{
    private int $warnAt = 1;

    private int $failAt = 10;

    public function warnWhenAtLeast(int $count): self
    {
        $this->warnAt = $count;

        return $this;
    }

    public function failWhenAtLeast(int $count): self
    {
        $this->failAt = $count;

        return $this;
    }

    public function run(): Result
    {
        $failed = DB::table('failed_jobs')->count();
        $result = Result::make()->meta(['failed' => $failed])->shortSummary((string) $failed);

        return match (true) {
            $failed >= $this->failAt => $result->failed("{$failed} background jobs failed."),
            $failed >= $this->warnAt => $result->warning("{$failed} background jobs failed."),
            default => $result->ok(),
        };
    }
}
