<?php

namespace App\Domain\Content\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * An internal note on a record, never shown publicly (PLAN.md §7: send back
 * with comments). A "send back" note explains what to change.
 *
 * @property int $id
 * @property string $commentable_type
 * @property string $commentable_id
 * @property ?string $locale
 * @property string $kind
 * @property string $body
 * @property ?int $user_id
 * @property CarbonImmutable $created_at
 * @property-read ?User $author
 */
class Comment extends Model
{
    public const KIND_COMMENT = 'comment';

    public const KIND_SEND_BACK = 'send_back';

    public const UPDATED_AT = null;

    protected $table = 'content_comments';

    protected $fillable = ['locale', 'kind', 'body'];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
