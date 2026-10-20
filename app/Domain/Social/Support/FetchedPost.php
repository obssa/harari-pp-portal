<?php

namespace App\Domain\Social\Support;

use Carbon\CarbonImmutable;

/**
 * A post as a fetcher read it, before it is stored.
 */
final readonly class FetchedPost
{
    /**
     * @param  list<string>  $imageUrls
     * @param  array<string, int>  $metrics
     */
    public function __construct(
        public string $externalId,
        public CarbonImmutable $postedAt,
        public string $text,
        public string $permalink,
        public array $imageUrls = [],
        public bool $hasVideo = false,
        public array $metrics = [],
    ) {}
}
