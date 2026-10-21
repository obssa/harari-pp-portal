<?php

namespace App\Domain\Api\Models;

use App\Domain\Api\Enums\ApiScope;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A key an outside app (the branch office's mobile app, an office kiosk)
 * uses to read the public API (ADR-0056). Only a keyed hash is stored; the
 * key itself is shown once, when it is issued.
 *
 * @property int $id
 * @property string $name
 * @property string $prefix
 * @property string $key_hash
 * @property list<string> $scopes
 * @property int $rate_per_minute
 * @property ?string $notes
 * @property ?CarbonImmutable $last_used_at
 * @property ?CarbonImmutable $expires_at
 * @property ?CarbonImmutable $revoked_at
 * @property ?int $created_by
 * @property-read ?User $creator
 */
class ApiKey extends Model
{
    use LogsActivity;

    /** Every key starts with this, so a leaked one is easy to recognise. */
    public const PREFIX = 'hpp_';

    protected $fillable = ['name', 'scopes', 'rate_per_minute', 'notes', 'expires_at'];

    protected $hidden = ['key_hash'];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'rate_per_minute' => 'integer',
            'last_used_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('integrations')
            ->logOnly(['name', 'prefix', 'scopes', 'rate_per_minute', 'expires_at', 'revoked_at'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function hash(string $plain): string
    {
        return hash_hmac('sha256', $plain, (string) config('app.key'));
    }

    /**
     * The key with this text, if it may still be used.
     */
    public static function findUsable(string $plain): ?self
    {
        if (! str_starts_with($plain, self::PREFIX) || strlen($plain) > 100) {
            return null;
        }

        $key = self::query()->where('key_hash', self::hash($plain))->first();

        return $key !== null && $key->isUsable() ? $key : null;
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function allows(ApiScope $scope): bool
    {
        return in_array($scope->value, $this->scopes, true);
    }
}
