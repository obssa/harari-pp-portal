<?php

namespace App\Domain\Social\Fetchers;

use App\Domain\Social\Models\SocialSource;
use App\Domain\Social\Support\FetchedPost;
use App\Settings\IntegrationSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Facebook pages through the Graph API with the page's own access token
 * (PLAN.md §4: never by scraping behind the login, ADR-0031). The token is
 * the source's own, otherwise the one under Integrations.
 */
final class FacebookFetcher implements SocialFetcher
{
    public const GRAPH = 'https://graph.facebook.com/v21.0/';

    public function __construct(private readonly IntegrationSettings $settings) {}

    public function fetch(SocialSource $source): array
    {
        $token = $source->credentials['token'] ?? $this->settings->meta_page_access_token;
        if (blank($token)) {
            throw new RuntimeException(__('social.error.facebook_token'));
        }

        $response = Http::timeout(20)->get(self::GRAPH.rawurlencode($source->handle).'/posts', [
            'fields' => 'id,message,created_time,permalink_url,full_picture,status_type',
            'limit' => 25,
            'access_token' => $token,
        ]);
        if (! $response->successful()) {
            throw new RuntimeException(__('social.error.facebook', ['message' => (string) $response->json('error.message', (string) $response->status())]));
        }

        $posts = [];
        foreach ((array) $response->json('data', []) as $item) {
            if (! is_array($item) || ! is_string($item['id'] ?? null) || ! is_string($item['permalink_url'] ?? null)) {
                continue;
            }
            try {
                $at = CarbonImmutable::parse((string) ($item['created_time'] ?? ''))->utc();
            } catch (Throwable) {
                continue;
            }
            $picture = is_string($item['full_picture'] ?? null) ? $item['full_picture'] : null;

            $posts[] = new FetchedPost(
                externalId: $item['id'],
                postedAt: $at,
                text: trim((string) ($item['message'] ?? '')),
                permalink: $item['permalink_url'],
                imageUrls: $picture !== null ? [$picture] : [],
                hasVideo: in_array($item['status_type'] ?? null, ['added_video', 'shared_video'], true),
            );
        }

        return $posts;
    }
}
