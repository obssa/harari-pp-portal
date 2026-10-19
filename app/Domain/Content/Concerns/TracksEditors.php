<?php

namespace App\Domain\Content\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Who created and who last changed a record (PLAN.md §10: created_by,
 * updated_by on every content table).
 *
 * @property ?int $created_by
 * @property ?int $updated_by
 */
trait TracksEditors
{
    public static function bootTracksEditors(): void
    {
        static::creating(function (self $model): void {
            $id = auth()->id();
            $model->created_by ??= is_int($id) ? $id : null;
        });

        static::saving(function (self $model): void {
            $id = auth()->id();
            if (is_int($id)) {
                $model->updated_by = $id;
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
