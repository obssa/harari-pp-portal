<?php

namespace App\Domain\Engagement\Models;

use App\Domain\Content\Concerns\BelongsToSite;
use App\Domain\Events\Models\EventRegistration;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Someone who asked for news by email or SMS (PLAN.md §2, §4, §10; ADR-0051).
 * Each channel counts only once confirmed; the private token opens the
 * preference centre. The audit log never records the contact details.
 *
 * @property string $id
 * @property string $locale
 * @property ?string $email
 * @property ?string $email_hash
 * @property ?string $phone
 * @property ?string $phone_hash
 * @property list<string> $channels
 * @property list<string> $topics
 * @property string $token
 * @property string $token_hash
 * @property ?string $code_hash
 * @property ?CarbonImmutable $code_expires_at
 * @property int $code_attempts
 * @property ?CarbonImmutable $code_sent_at
 * @property ?CarbonImmutable $email_confirmed_at
 * @property ?CarbonImmutable $phone_confirmed_at
 * @property string $source
 * @property ?CarbonImmutable $consented_at
 * @property ?CarbonImmutable $unsubscribed_at
 * @property ?CarbonImmutable $erased_at
 * @property CarbonImmutable $created_at
 */
class Subscriber extends Model
{
    use BelongsToSite;
    use HasUlids;
    use LogsActivity;

    protected $fillable = ['locale', 'email', 'email_hash', 'phone', 'phone_hash', 'channels', 'topics', 'source', 'consented_at'];

    protected $hidden = ['email', 'phone', 'token', 'code_hash'];

    protected function casts(): array
    {
        return [
            'email' => 'encrypted',
            'phone' => 'encrypted',
            'token' => 'encrypted',
            'channels' => 'array',
            'topics' => 'array',
            'code_attempts' => 'integer',
            'code_expires_at' => 'immutable_datetime',
            'code_sent_at' => 'immutable_datetime',
            'email_confirmed_at' => 'immutable_datetime',
            'phone_confirmed_at' => 'immutable_datetime',
            'consented_at' => 'immutable_datetime',
            'unsubscribed_at' => 'immutable_datetime',
            'erased_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** The keyed hash used for every lookup (same key as registrations, members and tickets). */
    public static function hash(string $value): string
    {
        return EventRegistration::hash($value);
    }

    /** A new private link token; returns it in clear, keeps it encrypted and hashed. */
    public function issueToken(): string
    {
        $token = Str::random(40);
        $this->forceFill(['token' => $token, 'token_hash' => self::hash($token)]);

        return $token;
    }

    public static function byToken(string $token): ?self
    {
        return self::query()->where('token_hash', self::hash($token))->whereNull('erased_at')->first();
    }

    /** Whether news may go to this person on a channel: chosen, confirmed and not unsubscribed. */
    public function reachableBy(string $channel): bool
    {
        if ($this->unsubscribed_at !== null || $this->erased_at !== null || ! in_array($channel, $this->channels, true)) {
            return false;
        }

        return $channel === 'email' ? $this->email_confirmed_at !== null && $this->email !== null : $this->phone_confirmed_at !== null && $this->phone !== null;
    }

    public function isConfirmed(): bool
    {
        return $this->email_confirmed_at !== null || $this->phone_confirmed_at !== null;
    }

    /**
     * Subscribers news may reach on the channel.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeReachable(Builder $query, string $channel): Builder
    {
        return $query->whereNull('unsubscribed_at')->whereNull('erased_at')->whereJsonContains('channels', $channel)
            ->whereNotNull($channel === 'email' ? 'email_confirmed_at' : 'phone_confirmed_at');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('newsletter')->logOnly(['locale', 'channels', 'topics', 'email_confirmed_at', 'phone_confirmed_at', 'unsubscribed_at'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
