<?php

namespace App\Domain\Social\Support;

use App\Domain\Social\Enums\Platform;
use App\Domain\Social\Fetchers\SocialFetcher;
use App\Domain\Social\Fetchers\TelegramFetcher;

/**
 * Which fetcher reads which network (ADR-0031). A network without one is
 * listed in the admin but not read yet.
 */
final class Fetchers
{
    /** @var array<string, class-string<SocialFetcher>> */
    private const DRIVERS = [
        'telegram' => TelegramFetcher::class,
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
