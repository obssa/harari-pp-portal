<?php

namespace App\Domain\Health\Checks;

use App\Domain\Health\Support\RecentErrors;
use Carbon\CarbonImmutable;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Errors written to the application log in the last 24 hours (ADR-0038):
 * any is worth a look, many mean something is broken.
 */
final class ErrorLogCheck extends Check
{
    private int $failAt = 50;

    private ?string $directory = null;

    public function failWhenAtLeast(int $count): self
    {
        $this->failAt = $count;

        return $this;
    }

    public function directory(string $directory): self
    {
        $this->directory = $directory;

        return $this;
    }

    public function run(): Result
    {
        $count = RecentErrors::countSince(CarbonImmutable::now()->subDay(), $this->directory);
        $result = Result::make()->meta(['errors_24h' => $count])->shortSummary((string) $count);

        return match (true) {
            $count >= $this->failAt => $result->failed("{$count} errors were logged in the last 24 hours."),
            $count > 0 => $result->warning("{$count} errors were logged in the last 24 hours."),
            default => $result->ok(),
        };
    }
}
