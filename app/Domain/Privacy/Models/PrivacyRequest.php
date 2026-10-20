<?php

namespace App\Domain\Privacy\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request to export or erase what the portal holds about a person
 * (ADR-0041). It keeps only a keyed hash of the email or phone searched,
 * what was found, who handled it and when.
 *
 * @property int $id
 * @property string $kind
 * @property string $subject_hash
 * @property array<string, int> $found
 * @property ?int $handled_by
 * @property CarbonImmutable $created_at
 * @property-read ?User $handler
 */
final class PrivacyRequest extends Model
{
    public const EXPORT = 'export';

    public const ERASE = 'erase';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['found' => 'array', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
