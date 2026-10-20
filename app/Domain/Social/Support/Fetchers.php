<?php

namespace App\Domain\Social\Support;

use App\Domain\Social\Enums\Platform;
use App\Domain\Social\Fetchers\FacebookFetcher;
use App\Domain\Social\Fetchers\SocialFetcher;
use App\Domain\Social\Fetchers\TelegramFetcher;
use App\Domain\Social\Fetchers\YouTubeFetcher;

/**
 * Which fetcher reads which network (ADR-0031). Networks without one
 * (TikTok, Instagram, X) get their posts added by address.
 */
final class Fetchers
{
    /** @var array<string, class-string<SocialFetcher>> */
    private const DRIVERS = [
        'telegram' => TelegramFetcher::class,
        'youtube' => YouTubeFetcher::class,
        'facebook' => FacebookFetcher::class,
    ];

    public function for(Platform $platform): ?SocialFetcher
    {
        $class = self::DRIVERS[$platform->value] ?? null;

        return $class !== null ? app($class) : null;
    }

    public function supports(Platform $platform): bool
    {
        return isset(self::DRIVERS[$platform->value]);
    }
}
