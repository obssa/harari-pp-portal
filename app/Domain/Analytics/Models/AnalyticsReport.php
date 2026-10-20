<?php

namespace App\Domain\Analytics\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A traffic report sent by email every week (on Mondays, for the last seven
 * days) or every month (on the 1st, for the month before), ADR-0040.
 *
 * @property int $id
 * @property string $name
 * @property string $dimension
 * @property ?string $locale_filter
 * @property string $frequency
 * @property list<string> $recipients
 * @property string $mail_locale
 * @property ?CarbonImmutable $last_sent_at
 * @property ?int $created_by
 * @property-read ?User $creator
 */
final class AnalyticsReport extends Model
{
    use LogsActivity;

    public const WEEKLY = 'weekly';

    public const MONTHLY = 'monthly';

    public const MAX_RECIPIENTS = 10;

    protected $fillable = ['name', 'dimension', 'locale_filter', 'frequency', 'recipients', 'mail_locale'];

    protected function casts(): array
    {
        return ['recipients' => 'array', 'last_sent_at' => 'immutable_datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The period the report covers when sent on a given day.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function period(CarbonImmutable $on): array
    {
        return $this->frequency === self::MONTHLY
            ? [$on->subMonthNoOverflow()->startOfMonth(), $on->subMonthNoOverflow()->endOfMonth()]
            : [$on->subDays(7)->startOfDay(), $on->subDay()->endOfDay()];
    }

    public function isDue(CarbonImmutable $on): bool
    {
        $due = $this->frequency === self::MONTHLY ? $on->day === 1 : $on->isMonday();

        return $due && ($this->last_sent_at === null || ! $this->last_sent_at->isSameDay($on));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('analytics')->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
