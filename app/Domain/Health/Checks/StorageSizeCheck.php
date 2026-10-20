<?php

namespace App\Domain\Health\Checks;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Symfony\Component\Finder\Finder;

/**
 * How much the media library, uploads and backups take (ADR-0037), against
 * the hosting quota when one is set (`PORTAL_STORAGE_QUOTA_MB`). The shared
 * host's disk is shared with other accounts, so its free space says little.
 */
final class StorageSizeCheck extends Check
{
    /** @var list<string> */
    private array $paths = [];

    private ?int $quotaMb = null;

    /**
     * @param  list<string>  $paths
     */
    public function paths(array $paths): self
    {
        $this->paths = $paths;

        return $this;
    }

    public function quotaMb(?int $megabytes): self
    {
        $this->quotaMb = $megabytes !== null && $megabytes > 0 ? $megabytes : null;

        return $this;
    }

    public function run(): Result
    {
        $megabytes = (int) round($this->bytes() / 1024 / 1024);
        $result = Result::make()->meta(['used_mb' => $megabytes, 'quota_mb' => $this->quotaMb]);

        if ($this->quotaMb === null) {
            return $result->shortSummary("{$megabytes} MB")->ok();
        }

        $percent = (int) round($megabytes / $this->quotaMb * 100);
        $result->shortSummary("{$megabytes} / {$this->quotaMb} MB ({$percent}%)");

        return match (true) {
            $percent >= 95 => $result->failed("Storage is {$percent}% of the quota."),
            $percent >= 80 => $result->warning("Storage is {$percent}% of the quota."),
            default => $result->ok(),
        };
    }

    private function bytes(): int
    {
        $existing = array_values(array_filter($this->paths, 'is_dir'));
        if ($existing === []) {
            return 0;
        }

        $bytes = 0;
        foreach (Finder::create()->files()->in($existing)->ignoreDotFiles(false) as $file) {
            $bytes += (int) $file->getSize();
        }

        return $bytes;
    }
}
