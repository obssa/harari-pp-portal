<?php

namespace App\Domain\Content\Concerns;

use App\Domain\Sites\Models\Site;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Content belongs to a site (PLAN.md §7, ADR-0008): new records take the
 * current site, and queries only see the current site's records.
 *
 * @property int $site_id
 */
trait BelongsToSite
{
    public static function bootBelongsToSite(): void
    {
        static::creating(function (self $model): void {
            $model->site_id ??= Site::current()->id;
        });

        static::addGlobalScope('site', function (Builder $query): void {
            $query->where($query->getModel()->qualifyColumn('site_id'), Site::current()->id);
        });
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
