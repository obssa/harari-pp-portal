<?php

namespace App\Domain\Content\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A saved version of one language of a record (PLAN.md §7: revision history
 * with diff and restore). Rows are never edited.
 *
 * @property int $id
 * @property string $revisionable_type
 * @property string $revisionable_id
 * @property ?string $locale
 * @property array<string, mixed> $data
 * @property ?string $note
 * @property ?int $created_by
 * @property CarbonImmutable $created_at
 * @property-read ?User $author
 */
class Revision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['locale', 'data', 'note', 'created_by'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function revisionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
