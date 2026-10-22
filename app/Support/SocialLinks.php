<?php

namespace App\Support;

use App\Settings\SiteSettings;
use Throwable;

/**
 * The party's pages on social media (ADR-0072), as set under Settings → Site,
 * the same way everywhere they are shown: in a fixed order, with the
 * platform's name, a short handle, its brand colour for the icon and a
 * darker shade that carries white text at 4.5:1.
 */
final class SocialLinks
{
    /** Platform => [name, icon colour, text-safe colour, action label key]. Brand names are the same in every language. */
    public const PLATFORMS = [
        'facebook' => ['Facebook', '#1877F2', '#1466D8', 'social.action.follow'],
        'telegram' => ['Telegram', '#229ED9', '#16719F', 'social.action.join'],
        'youtube' => ['YouTube', '#FF0033', '#D90429', 'social.action.subscribe'],
        'x' => ['X', '#0F1419', '#0F1419', 'social.action.follow'],
        'tiktok' => ['TikTok', '#010101', '#010101', 'social.action.follow'],
        'instagram' => ['Instagram', '#E1306C', '#B4235A', 'social.action.follow'],
    ];

    /**
     * @return array<string, array{name: string, url: string, handle: string, color: string, dark: string, action: string}>
     */
    public static function all(): array
    {
        try {
            $links = app(SiteSettings::class)->social_links;
        } catch (Throwable) {
            return [];
        }

        $all = [];
        foreach (self::PLATFORMS as $platform => [$name, $color, $dark, $action]) {
            $url = $links[$platform] ?? null;
            if (is_string($url) && str_starts_with($url, 'https://')) {
                $all[$platform] = ['name' => $name, 'url' => $url, 'handle' => self::handle($platform, $url), 'color' => $color, 'dark' => $dark, 'action' => __($action)];
            }
        }

        return $all;
    }

    public static function url(string $platform): ?string
    {
        return self::all()[$platform]['url'] ?? null;
    }

    /**
     * The Facebook page and how its posts load (off, ask, auto), or null.
     *
     * @return array{page: string, mode: string}|null
     */
    public static function facebookFeed(): ?array
    {
        $page = self::url('facebook');
        try {
            $mode = app(SiteSettings::class)->facebook_feed;
        } catch (Throwable) {
            $mode = 'auto';
        }
        $mode = in_array($mode, SiteSettings::FACEBOOK_FEED, true) ? $mode : 'auto';

        return $page !== null && $mode !== 'off' ? ['page' => $page, 'mode' => $mode] : null;
    }

    /** The channel's public preview (t.me/s/name), readable without the app. */
    public static function telegramPreview(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        return preg_match('#^https://t\.me/([A-Za-z0-9_]{4,})/?$#', $url, $match) === 1 ? "https://t.me/s/{$match[1]}" : $url;
    }

    /** A short name for the page: @channel, @handle, or the site's name for Facebook pages with numeric ids. */
    public static function handle(string $platform, string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        if ($platform === 'facebook' && ($path === '' || str_contains($url, 'profile.php') || str_starts_with($path, 'p/'))) {
            return __('site.name');
        }
        $first = explode('/', $path)[0];

        return $first === '' ? __('site.name') : '@'.ltrim($first, '@');
    }
}
