<?php

namespace App\Services\Cdn;

use App\Settings\IntegrationSettings;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cloudflare's cache for this site (PLAN.md §9 caching, ADR-0039). The zone
 * is shared with other sites, so only this site's host is purged, never the
 * whole zone. Off until a zone id and an API token with "Cache Purge"
 * permission are saved under Integrations.
 */
final class CloudflareCache
{
    public const API = 'https://api.cloudflare.com/client/v4/';

    public function __construct(private readonly IntegrationSettings $settings) {}

    public function isConfigured(): bool
    {
        return filled($this->settings->cloudflare_zone_id) && filled($this->settings->cloudflare_api_token);
    }

    public static function host(): string
    {
        return (string) parse_url((string) config('app.url'), PHP_URL_HOST);
    }

    /** Drops every cached page and file of this site's host. */
    public function purgeHost(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Cloudflare is not set up.');
        }

        $response = Http::timeout(20)
            ->withToken((string) $this->settings->cloudflare_api_token)
            ->post(self::API.'zones/'.rawurlencode((string) $this->settings->cloudflare_zone_id).'/purge_cache', ['hosts' => [self::host()]]);

        if (! $response->successful() || $response->json('success') !== true) {
            $message = (string) ($response->json('errors.0.message') ?? $response->status());

            throw new RuntimeException("Cloudflare refused the purge: {$message}");
        }
    }
}
