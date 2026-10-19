<?php

namespace App\Console\Commands;

use App\Domain\Import\Actions\RunImport;
use App\Domain\Import\Models\ContentSource;
use Illuminate\Console\Command;

/**
 * Imports new stories from the public channels as drafts (ADR-0022). Runs
 * every half hour; `--pages` reads further back on a first run.
 */
final class ImportContentCommand extends Command
{
    protected $signature = 'content:import {--source= : Only this channel} {--pages=1 : Pages to read back (1-10)}';

    protected $description = 'Import new stories from the public channels as draft posts';

    public function handle(RunImport $import): int
    {
        $only = null;
        if (filled($this->option('source'))) {
            $only = ContentSource::query()->where('handle', (string) $this->option('source'))->first();
            if ($only === null) {
                $this->error('No such source.');

                return self::FAILURE;
            }
        }

        $failed = false;
        foreach ($import->handle($only, (int) $this->option('pages')) as $handle => $result) {
            $this->line(sprintf('%s: %d imported, %d duplicates, %d skipped%s', $handle, $result['imported'], $result['duplicates'], $result['skipped'], $result['error'] ? ' — '.$result['error'] : ''));
            $failed = $failed || $result['error'] !== null;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
