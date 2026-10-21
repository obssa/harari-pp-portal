<?php

namespace App\Console\Commands;

use App\Domain\Content\Actions\LoadContentPack;
use Illuminate\Console\Command;

/**
 * Loads the records of `database/content/pack.json` that are not loaded
 * yet (ADR-0063). Safe to run on every deploy.
 */
final class LoadContentPackCommand extends Command
{
    protected $signature = 'content:load-pack {--path= : Another pack file}';

    protected $description = 'Load the content pack records that are not loaded yet';

    public function handle(LoadContentPack $pack): int
    {
        $path = (string) ($this->option('path') ?: database_path('content/pack.json'));

        if (! is_file($path)) {
            $this->warn("No content pack at {$path}.");

            return self::SUCCESS;
        }
        if (LoadContentPack::actor() === null) {
            $this->warn('No Super Admin yet; the content pack waits for one.');

            return self::SUCCESS;
        }

        $counts = $pack->handle($path);

        $this->line($counts === [] ? 'Content pack: nothing new.' : 'Content pack: '.collect($counts)->map(fn (int $n, string $kind): string => "{$n} {$kind}")->implode(', ').'.');

        return self::SUCCESS;
    }
}
