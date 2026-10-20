<?php

namespace App\Domain\Health\Checks;

use App\Services\Cdn\CloudflareCache;
use App\Services\Cdn\PurgeEdgeCache;
use Illuminate\Support\Facades\Cache;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Whether published changes reach visitors at once (ADR-0039): Cloudflare
 * purges set up and the last one accepted. Without them pages refresh by
 * their cache headers within ten minutes.
 */
final class EdgeCacheCheck extends Check
{
    public function run(): Result
    {
        if (! app(CloudflareCache::class)->isConfigured()) {
            return Result::make()->shortSummary('off')->ok('Cloudflare purges are not set up: pages refresh within ten minutes.');
        }

        $last = Cache::get(PurgeEdgeCache::LAST);
        if (is_array($last) && ($last['ok'] ?? true) === false) {
            return Result::make()->meta($last)->shortSummary('purge failed')->warning((string) ($last['error'] ?? 'The last purge failed.'));
        }

        return Result::make()->meta(is_array($last) ? $last : [])->shortSummary('on')->ok();
    }
}
