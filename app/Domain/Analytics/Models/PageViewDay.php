<?php

namespace App\Domain\Analytics\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * Views of one page in one language on one day (ADR-0040). Kept two years.
 *
 * @property int $id
 * @property CarbonImmutable $date
 * @property string $locale
 * @property string $path
 * @property string $section
 * @property int $views
 */
final class PageViewDay extends Model
{
    use MassPrunable;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'immutable_date', 'views' => 'integer'];
    }

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return self::query()->where('date', '<', now()->subYears(2)->toDateString());
    }
}
