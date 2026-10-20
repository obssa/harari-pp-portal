<?php

namespace App\Domain\Translation\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One translation file brought in (ADR-0033): first previewed, then applied
 * or dropped, and possibly rolled back.
 *
 * @property int $id
 * @property string $locale
 * @property string $file_name
 * @property string $format
 * @property string $status pending, applied, rolled_back, discarded
 * @property int $added
 * @property int $changed
 * @property int $removed
 * @property ?array<string, array{old: string, new: string}> $changes
 * @property ?list<array{key: string, problem: string}> $problems
 * @property ?array<string, ?string> $snapshot the admin's earlier value per key, null when there was none
 * @property ?int $user_id
 * @property ?CarbonImmutable $applied_at
 * @property ?CarbonImmutable $rolled_back_at
 * @property CarbonImmutable $created_at
 * @property-read ?User $user
 */
class TranslationImport extends Model
{
    use LogsActivity;

    public const PENDING = 'pending';

    public const APPLIED = 'applied';

    public const ROLLED_BACK = 'rolled_back';

    public const DISCARDED = 'discarded';

    protected $fillable = ['locale', 'file_name', 'format', 'status', 'added', 'changed', 'removed', 'changes', 'problems', 'snapshot', 'user_id'];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'problems' => 'array',
            'snapshot' => 'array',
            'applied_at' => 'immutable_datetime',
            'rolled_back_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('translations')
            ->logOnly(['locale', 'file_name', 'status', 'added', 'changed', 'removed'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
