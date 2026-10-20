<?php

namespace App\Console\Commands;

use App\Domain\Social\Actions\FetchSocialSource;
use App\Domain\Social\Models\SocialSource;
use Illuminate\Console\Command;

/**
 * Reads the social sources whose interval has passed (ADR-0031; runs every
 * fifteen minutes from the scheduler). --all reads every enabled source.
 */
final class FetchSocialCommand extends Command
{
    protected $signature = 'social:fetch {--all : Read every enabled source now}';

    protected $description = 'Read new posts from the social media sources that are due';

    public function handle(FetchSocialSource $fetch): int
    {
        $sources = $this->option('all')
            ? SocialSource::query()->where('enabled', true)->get()
            : SocialSource::query()->due()->get();

        foreach ($sources as $source) {
            $result = $fetch->handle($source);
            $line = "{$source->platform->value}/{$source->handle}: {$result['added']} new, {$result['updated']} updated";
            $result['error'] !== null ? $this->components->warn($line.' — '.$result['error']) : $this->components->info($line);
        }
        if ($sources->isEmpty()) {
            $this->components->info('No source is due.');
        }

        return self::SUCCESS;
    }
}
