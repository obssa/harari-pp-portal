<?php

namespace App\Domain\Social\Jobs;

use App\Domain\Social\Actions\SharePost;
use App\Domain\Social\Models\SocialShare;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Posts queued shares one after the other (ADR-0059). Each share records
 * its own outcome, so one refused account does not stop the others.
 */
final class DeliverSocialShare implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    /**
     * @param  list<int>  $shareIds
     */
    public function __construct(public readonly array $shareIds) {}

    public function handle(SharePost $shares): void
    {
        SocialShare::query()->whereKey($this->shareIds)->where('status', SocialShare::QUEUED)
            ->with(['source', 'post.translations'])
            ->each(fn (SocialShare $share) => $shares->deliver($share));
    }
}
