<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Api\Models\ApiKey;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberToken;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Signs a member in to the member app once their SMS code is checked
 * (ADR-0057), and out again. The token's text is returned once.
 */
final class IssueMemberToken
{
    /** Devices a member may be signed in on at once; the oldest goes first. */
    public const DEVICES = 5;

    /**
     * @return array{0: MemberToken, 1: string} the sign-in and its token
     */
    public function handle(Member $member, ?ApiKey $key, string $device): array
    {
        $plain = MemberToken::PREFIX.Str::random(48);
        $device = trim(mb_substr($device, 0, 100));

        $token = MemberToken::query()->create([
            'member_id' => $member->id,
            'api_key_id' => $key?->id,
            'device' => $device !== '' ? $device : __('api.member.device_default'),
            'token_hash' => MemberToken::hash($plain),
            'expires_at' => CarbonImmutable::now()->addDays((int) config('portal.api.member_token_days')),
        ]);

        $keep = $member->appTokens()->latest('id')->limit(self::DEVICES)->pluck('id');
        $member->appTokens()->whereNotIn('id', $keep)->delete();
        activity('membership')->performedOn($member)->event('app_sign_in')->log('Member signed in to the member app');

        return [$token, $plain];
    }

    public function revoke(MemberToken $token): void
    {
        $token->delete();
    }

    /** Signs the member out on every device; returns how many. */
    public function revokeAll(Member $member): int
    {
        return $member->appTokens()->delete();
    }
}
