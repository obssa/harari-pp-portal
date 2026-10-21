<?php

namespace App\Http\Middleware;

use App\Domain\Membership\Models\MemberToken;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Member app requests (ADR-0057) carry the member's token as a Bearer token,
 * besides the app's API key. A token stops working when it expires, when the
 * member signs out, or when the member may no longer use the portal.
 */
final class AuthenticateMemberToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = MemberToken::findUsable((string) $request->bearerToken());
        if ($token === null) {
            return response()->json(['message' => __('api.error.member_token')], 401, [
                'WWW-Authenticate' => 'Bearer',
                'Cache-Control' => 'no-store',
            ]);
        }

        if ($token->last_used_at === null || $token->last_used_at->lt(CarbonImmutable::now()->subMinute())) {
            MemberToken::query()->whereKey($token->id)->update(['last_used_at' => CarbonImmutable::now()]);
        }

        $request->attributes->set('member', $token->member);
        $request->attributes->set('member_token', $token);

        return $next($request);
    }
}
