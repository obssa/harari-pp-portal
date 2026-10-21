<?php

namespace App\Domain\Sites\Support;

use App\Domain\Sites\Models\Site;

/**
 * Holds the site of the request or job being handled (ADR-0061). Bound as a
 * scoped instance, so a long-running worker (Octane, the queue worker)
 * starts every request and job without the previous one's site.
 */
final class CurrentSite
{
    public ?Site $site = null;
}
