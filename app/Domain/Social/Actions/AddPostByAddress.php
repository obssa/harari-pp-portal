<?php

namespace App\Domain\Social\Actions;

use App\Domain\Social\Enums\Platform;
use App\Domain\Social\Models\SocialPost;
use App\Domain\Social\Models\SocialSource;
use App\Domain\Social\Support\FetchedPost;
use App\Settings\IntegrationSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Adds one post of a source by its address (ADR-0031), for networks the
 * portal does not read on its own (TikTok, Instagram, X) or for a post
 * the reading missed. Title and picture come from the network's public
 * oEmbed answer when it gives one; the editor's own text otherwise.
 */
final class AddPostByAddress
{
    /** Hosts each network's post addresses use. */
    private const HOSTS = [
        'telegram' => ['t.me'],
        'youtube' => ['www.youtube.com', 'youtube.com', 'youtu.be', 'm.youtube.com'],
        'facebook' => ['www.facebook.com', 'facebook.com', 'm.facebook.com', 'fb.watch'],
        'tiktok' => ['www.tiktok.com', 'tiktok.com', 'vm.tiktok.com'],
        'instagram' => ['www.instagram.com', 'instagram.com'],
        'x' => ['x.com', 'twitter.com', 'www.x.com', 'mobile.twitter.com'],
    ];

    public function __construct(
        private readonly FetchSocialSource $store,
        private readonly IntegrationSettings $settings,
    ) {}

    public function handle(SocialSource $source, string $url, ?string $text = null, ?string $postedAt = null): SocialPost
    {
        $url = trim($url);
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (! str_starts_with($url, 'https://') || filter_var($url, FILTER_VALIDATE_URL) === false || ! in_array($host, self::HOSTS[$source->platform->value], true)) {
            throw ValidationException::withMessages(['url' => __('social.post.validation.url', ['platform' => $source->platform->label()])]);
        }

        $externalId = 'url:'.hash('sha256', strtok($url, '?#') ?: $url);
        if ($source->posts()->where('external_id', $externalId)->exists()) {
            throw ValidationException::withMessages(['url' => __('social.post.validation.known')]);
        }

        $embed = $this->oembed($source->platform, $url);
        $title = trim((string) ($text ?: ($embed['title'] ?? '')));
        try {
            $at = filled($postedAt) ? CarbonImmutable::parse((string) $postedAt)->utc() : CarbonImmutable::now('UTC');
        } catch (Throwable) {
            throw ValidationException::withMessages(['posted_at' => __('organisation.validation.date')]);
        }

        $post = $this->store->store($source, new FetchedPost(
            externalId: $externalId,
            postedAt: $at,
            text: mb_substr($title, 0, 5000),
            permalink: $url,
            imageUrls: is_string($embed['thumbnail_url'] ?? null) ? [$embed['thumbnail_url']] : [],
            hasVideo: in_array($source->platform, [Platform::TikTok, Platform::YouTube], true),
        ));

        return $post;
    }

    /**
     * The network's public description of a post, when it gives one.
     *
     * @return array<string, mixed>
     */
    private function oembed(Platform $platform, string $url): array
    {
        $endpoint = match ($platform) {
            Platform::TikTok => ['https://www.tiktok.com/oembed', ['url' => $url]],
            Platform::YouTube => ['https://www.youtube.com/oembed', ['url' => $url, 'format' => 'json']],
            Platform::X => ['https://publish.twitter.com/oembed', ['url' => $url, 'omit_script' => 'true']],
            Platform::Instagram => filled($this->settings->meta_page_access_token)
                ? ['https://graph.facebook.com/v21.0/instagram_oembed', ['url' => $url, 'access_token' => $this->settings->meta_page_access_token]]
                : null,
            default => null,
        };
        if ($endpoint === null) {
            return [];
        }

        try {
            $data = Http::timeout(15)->get($endpoint[0], $endpoint[1])->json();
        } catch (Throwable) {
            return [];
        }
        if (! is_array($data)) {
            return [];
        }
        // X answers with the post as HTML; keep its words only.
        if ($platform === Platform::X && is_string($data['html'] ?? null)) {
            $data['title'] = trim(html_entity_decode(strip_tags((string) preg_replace('/<a [^>]*>[^<]*<\/a>\s*$/', '', explode('&mdash;', $data['html'])[0])), ENT_QUOTES | ENT_HTML5));
        }

        return $data;
    }
}
