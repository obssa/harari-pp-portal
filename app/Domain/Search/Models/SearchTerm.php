<?php

namespace App\Domain\Search\Models;

use App\Domain\Content\Concerns\BelongsToSite;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * What visitors searched for, counted per language (PLAN.md §7 search
 * insights: top and zero-result queries). Only the normalized words are
 * kept; nothing about who searched.
 *
 * @property int $id
 * @property string $locale
 * @property string $term
 * @property int $searches
 * @property int $last_results
 * @property CarbonImmutable $last_searched_at
 */
class SearchTerm extends Model
{
    use BelongsToSite;
    use Prunable;

    /** Terms nobody searched for in this many days are removed. */
    public const KEEP_DAYS = 365;

    protected $fillable = ['locale', 'term', 'searches', 'last_results', 'last_searched_at'];

    protected function casts(): array
    {
        return [
            'searches' => 'integer',
            'last_results' => 'integer',
            'last_searched_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('last_searched_at', '<', now()->subDays(self::KEEP_DAYS));
    }
}
