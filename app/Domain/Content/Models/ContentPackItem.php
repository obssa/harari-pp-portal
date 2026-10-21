<?php

namespace App\Domain\Content\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A record the content pack has loaded (ADR-0063), by its key, so it is
 * never loaded twice and what editors change or delete is never put back.
 *
 * @property int $id
 * @property string $key
 * @property ?string $model_type
 * @property ?string $model_id
 * @property CarbonImmutable $loaded_at
 */
class ContentPackItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['key', 'model_type', 'model_id', 'loaded_at'];

    protected function casts(): array
    {
        return ['loaded_at' => 'immutable_datetime'];
    }

    public static function remember(string $key, ?Model $model): self
    {
        return self::query()->create([
            'key' => $key,
            'model_type' => $model?->getMorphClass(),
            'model_id' => $model !== null ? (string) $model->getKey() : null,
            'loaded_at' => now(),
        ]);
    }
}
