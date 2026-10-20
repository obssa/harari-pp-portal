<?php

namespace App\Domain\Analytics\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * Views on one day by referring site, kind of device or country, per
 * language (ADR-0040). Kept two years.
 *
 * @property int $id
 * @property CarbonImmutable $date
 * @property string $locale
 * @property string $dimension
 * @property string $value
 * @property int $views
 */
final class TrafficDay extends Model
{
    use MassPrunable;

    public const REFERRER = 'referrer';

    public const DEVICE = 'device';

    public const COUNTRY = 'country';

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
