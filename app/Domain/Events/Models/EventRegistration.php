<?php

namespace App\Domain\Events\Models;

use App\Domain\Events\Enums\RegistrationStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One person's place at an event (ADR-0025). Contact details and the ticket
 * code are encrypted at rest; the audit log records status changes only,
 * never the personal details.
 *
 * @property string $id
 * @property string $event_id
 * @property string $locale
 * @property string $name
 * @property ?string $phone
 * @property ?string $email
 * @property ?string $phone_hash
 * @property ?string $email_hash
 * @property RegistrationStatus $status
 * @property string $token
 * @property string $token_hash
 * @property ?CarbonImmutable $checked_in_at
 * @property ?int $checked_in_by
 * @property ?CarbonImmutable $promoted_at
 * @property ?CarbonImmutable $reminded_at
 * @property ?CarbonImmutable $cancelled_at
 * @property CarbonImmutable $created_at
 * @property-read Event $event
 * @property-read ?User $checkedInBy
 */
class EventRegistration extends Model
{
    use HasUlids;
    use LogsActivity;

    protected $fillable = ['locale', 'name', 'phone', 'email', 'phone_hash', 'email_hash', 'status', 'token', 'token_hash'];

    protected $hidden = ['name', 'phone', 'email', 'token', 'token_hash', 'phone_hash', 'email_hash'];

    protected function casts(): array
    {
        return [
            'name' => 'encrypted',
            'phone' => 'encrypted',
            'email' => 'encrypted',
            'token' => 'encrypted',
            'status' => RegistrationStatus::class,
            'checked_in_at' => 'immutable_datetime',
            'promoted_at' => 'immutable_datetime',
            'reminded_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('events')
            ->logOnly(['status', 'checked_in_at'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    /** A keyed hash, so equal values can be found without decrypting them. */
    public static function hash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', RegistrationStatus::Cancelled->value);
    }

    public static function findByToken(string $token): ?self
    {
        return $token === '' ? null : self::query()->where('token_hash', self::hash($token))->first();
    }

    /** People ahead of this one on the waiting list, plus one. */
    public function waitlistPosition(): ?int
    {
        if ($this->status !== RegistrationStatus::Waitlisted) {
            return null;
        }

        return self::query()
            ->where('event_id', $this->event_id)
            ->where('status', RegistrationStatus::Waitlisted->value)
            ->where(fn (Builder $q) => $q->where('created_at', '<', $this->created_at)
                ->orWhere(fn (Builder $same) => $same->where('created_at', $this->created_at)->where('id', '<', $this->id)))
            ->count() + 1;
    }
}
