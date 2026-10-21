<?php

namespace App\Domain\Membership\Models;

use App\Domain\Content\Concerns\BelongsToSite;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * An import of an existing membership roll (PLAN.md §5, ADR-0047). The rows
 * checked from the file are kept encrypted until the import is applied or
 * discarded; the problems list holds row numbers and reasons only.
 *
 * @property int $id
 * @property ?int $user_id
 * @property string $file_name
 * @property string $status
 * @property array{id_type: string, calendar: string, joined_on: string, mapping: array<string, int|null>} $settings
 * @property ?list<array<string, mixed>> $rows
 * @property list<array{row: int, field: string, problem: string, level: string}> $problems
 * @property int $total
 * @property int $ready
 * @property int $with_problems
 * @property int $duplicates
 * @property int $created_count
 * @property ?CarbonImmutable $applied_at
 * @property ?CarbonImmutable $rolled_back_at
 * @property CarbonImmutable $created_at
 * @property-read ?User $user
 */
class MemberImport extends Model
{
    use BelongsToSite;
    use LogsActivity;

    public const CHECKED = 'checked';

    public const APPLYING = 'applying';

    public const APPLIED = 'applied';

    public const FAILED = 'failed';

    public const ROLLED_BACK = 'rolled_back';

    public const DISCARDED = 'discarded';

    /** Days during which an applied import can still be rolled back. */
    public const ROLLBACK_DAYS = 30;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'rows' => 'encrypted:array',
            'problems' => 'array',
            'total' => 'integer',
            'ready' => 'integer',
            'with_problems' => 'integer',
            'duplicates' => 'integer',
            'created_count' => 'integer',
            'applied_at' => 'immutable_datetime',
            'rolled_back_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function canRollBack(): bool
    {
        return $this->status === self::APPLIED && $this->applied_at !== null
            && $this->applied_at->greaterThan(CarbonImmutable::now()->subDays(self::ROLLBACK_DAYS));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Member, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class, 'import_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('membership')
            ->logOnly(['file_name', 'status', 'total', 'ready', 'with_problems', 'duplicates', 'created_count'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
