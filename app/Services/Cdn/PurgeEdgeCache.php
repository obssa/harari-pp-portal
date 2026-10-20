<?php

namespace App\Services\Cdn;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * One purge for a burst of changes (ADR-0039): saving a post touches several
 * records, so changes only ask for a purge half a minute later and while one
 * is waiting no other is queued. The outcome shows on the health page.
 */
final class PurgeEdgeCache implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public const LAST = 'portal:edge-purge';

    public int $tries = 2;

    public int $backoff = 60;

    public int $uniqueFor = 300;

    /** Asks for a purge if Cloudflare is set up; nothing happens otherwise. */
    public static function soon(): void
    {
        if (app(CloudflareCache::class)->isConfigured()) {
            self::dispatch()->delay(now()->addSeconds(30));
        }
    }

    public function handle(CloudflareCache $cloudflare): void
    {
        try {
            $cloudflare->purgeHost();
            Cache::forever(self::LAST, ['at' => CarbonImmutable::now()->toIso8601String(), 'ok' => true, 'error' => null]);
        } catch (Throwable $e) {
            $error = Str::limit($e->getMessage(), 300);
            Cache::forever(self::LAST, ['at' => CarbonImmutable::now()->toIso8601String(), 'ok' => false, 'error' => $error]);
            Log::warning('Cloudflare purge failed', ['error' => $error]);
        }
    }
}
