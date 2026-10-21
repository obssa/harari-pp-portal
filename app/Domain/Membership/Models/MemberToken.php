<?php

namespace App\Domain\Membership\Models;

use App\Domain\Api\Models\ApiKey;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member signed in to the member app on one device (ADR-0057). The app
 * sends the token as a Bearer token; only its keyed hash is stored, and
 * the device name is encrypted.
 *
 * @property int $id
 * @property string $member_id
 * @property ?int $api_key_id
 * @property string $device
 * @property string $token_hash
 * @property ?CarbonImmutable $last_used_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable $created_at
 * @property-read Member $member
 * @property-read ?ApiKey $apiKey
 */
class MemberToken extends Model
{
    /** Every member token starts with this, unlike API keys. */
    public const PREFIX = 'hppm_';

    protected $guarded = ['id'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'device' => 'encrypted',
            'last_used_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return BelongsTo<ApiKey, $this>
     */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    public static function hash(string $plain): string
    {
        return hash_hmac('sha256', 'member-token:'.$plain, (string) config('app.key'));
    }

    /**
     * The token with this text, if it has not expired and its member may
     * still use the portal.
     */
    public static function findUsable(string $plain): ?self
    {
        if (! str_starts_with($plain, self::PREFIX) || strlen($plain) > 100) {
            return null;
        }

        $token = self::query()->where('token_hash', self::hash($plain))->with('member')->first();
        if ($token === null || $token->expires_at->isPast() || ! $token->member->canUsePortal()) {
            return null;
        }

        return $token;
    }
}
