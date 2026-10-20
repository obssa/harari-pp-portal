<?php

namespace App\Domain\Social\Queries;

use App\Domain\Social\Enums\Platform;
use App\Domain\Social\Models\SocialPost;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The posts the public may see (ADR-0032): from enabled sources, not
 * hidden, pinned ones first, newest next.
 */
final class SocialFeed
{
    /**
     * @return Builder<SocialPost>
     */
    public static function query(?Platform $platform = null, ?string $officeId = null, ?string $leagueId = null): Builder
    {
        return SocialPost::query()->visible()
            ->with(['source', 'image'])
            ->when($platform !== null || $officeId !== null || $leagueId !== null, fn (Builder $q) => $q->whereHas('source', fn (Builder $s) => $s
                ->when($platform !== null, fn (Builder $p) => $p->where('platform', $platform?->value))
                ->when($officeId !== null, fn (Builder $p) => $p->where('office_id', $officeId))
                ->when($leagueId !== null, fn (Builder $p) => $p->where('league_id', $leagueId))))
            ->orderByDesc('is_pinned')
            ->orderByDesc('posted_at');
    }

    /**
     * @return Collection<int, SocialPost>
     */
    public static function latest(int $limit, ?string $officeId = null, ?string $leagueId = null): Collection
    {
        return self::query(officeId: $officeId, leagueId: $leagueId)->limit($limit)->get();
    }
}
