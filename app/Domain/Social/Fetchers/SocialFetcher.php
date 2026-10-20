<?php

namespace App\Domain\Social\Fetchers;

use App\Domain\Social\Models\SocialSource;
use App\Domain\Social\Support\FetchedPost;

/**
 * Reads the latest public posts of one account (PLAN.md §9: a fetcher per
 * platform, ADR-0031). Fetchers only read what the network publishes for
 * everyone, or what the API allows with the account's own token.
 */
interface SocialFetcher
{
    /**
     * @return list<FetchedPost> newest first
     */
    public function fetch(SocialSource $source): array;
}
