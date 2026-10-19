<?php

namespace App\Console\Commands;

use App\Domain\Content\Workflow\ContentWorkflow;
use Illuminate\Console\Command;

/**
 * Publishes scheduled languages whose time has come (runs every minute
 * from the scheduler, ADR-0013).
 */
final class PublishDueContentCommand extends Command
{
    protected $signature = 'content:publish-due';

    protected $description = 'Publish scheduled posts whose publication time has passed';

    public function handle(ContentWorkflow $workflow): int
    {
        $count = $workflow->publishDue();
        $this->components->info("Published: {$count}");

        return self::SUCCESS;
    }
}
