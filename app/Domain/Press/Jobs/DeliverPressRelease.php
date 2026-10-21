<?php

namespace App\Domain\Press\Jobs;

use App\Domain\Press\Actions\DistributePressRelease;
use App\Domain\Press\Models\PressDistribution;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Sends a press release to the media contacts chosen (ADR-0054). Media lists
 * are short, so one job sends them all.
 */
final class DeliverPressRelease implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    /**
     * @param  list<int>  $distributionIds
     */
    public function __construct(public readonly array $distributionIds) {}

    public function handle(DistributePressRelease $press): void
    {
        $distributions = PressDistribution::query()->whereKey($this->distributionIds)->where('status', PressDistribution::QUEUED)
            ->with(['contact', 'post.translations'])->get();
        foreach ($distributions as $distribution) {
            $press->deliver($distribution);
        }
    }
}
