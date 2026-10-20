<?php

namespace App\Domain\Translation\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One change to one language of an interface string (ADR-0033).
 *
 * @property int $id
 * @property string $key
 * @property string $locale
 * @property ?string $old_text
 * @property ?string $new_text
 * @property ?string $source_text
 * @property string $origin editor, import or machine
 * @property ?int $user_id
 * @property ?CarbonImmutable $created_at
 * @property-read ?User $user
 */
class UiTranslationRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['key', 'locale', 'old_text', 'new_text', 'source_text', 'origin', 'user_id'];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
