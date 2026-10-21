<?php

namespace App\Http\Middleware;

use App\Domain\Api\Enums\ApiScope;
use App\Domain\Api\Models\ApiKey;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * The API key in the X-Api-Key header must be valid and allow the scope of
 * the route (ADR-0056). Each key has its own requests-per-minute limit; an
 * address that keeps sending wrong keys is stopped for a minute.
 */
final class AuthenticateApiKey
{
    /** Wrong keys a minute from one address before it is refused. */
    public const FAILURES_PER_MINUTE = 20;

    public const HEADER = 'X-Api-Key';

    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $failures = 'api-failed:'.$request->ip();
        if (RateLimiter::tooManyAttempts($failures, self::FAILURES_PER_MINUTE)) {
            return self::error(429, __('api.error.too_many'), ['Retry-After' => (string) RateLimiter::availableIn($failures)]);
        }

        $key = ApiKey::findUsable((string) $request->header(self::HEADER, ''));
        if ($key === null) {
            RateLimiter::hit($failures, 60);

            return self::error(401, __('api.error.unauthenticated'), ['WWW-Authenticate' => 'ApiKey header="'.self::HEADER.'"']);
        }

        if (! $key->allows(ApiScope::from($scope))) {
            return self::error(403, __('api.error.scope'));
        }

        $limit = 'api-key:'.$key->id;
        $max = max(1, $key->rate_per_minute);
        if (RateLimiter::tooManyAttempts($limit, $max)) {
            return self::error(429, __('api.error.too_many'), [
                'Retry-After' => (string) RateLimiter::availableIn($limit),
                'X-RateLimit-Limit' => (string) $max,
                'X-RateLimit-Remaining' => '0',
            ]);
        }
        RateLimiter::hit($limit, 60);

        // Written at most once a minute, and without an audit entry for every request.
        if ($key->last_used_at === null || $key->last_used_at->lt(CarbonImmutable::now()->subMinute())) {
            ApiKey::query()->whereKey($key->id)->update(['last_used_at' => CarbonImmutable::now()]);
        }

        $request->attributes->set('api_key', $key);

        $response = $next($request);
        $response->headers->set('X-RateLimit-Limit', (string) $max);
        $response->headers->set('X-RateLimit-Remaining', (string) RateLimiter::remaining($limit, $max));

        return $response;
    }

    /**
     * @param  array<string, string>  $headers
     */
    private static function error(int $status, string $message, array $headers = []): JsonResponse
    {
        return response()->json(['message' => $message], $status, [...$headers, 'Cache-Control' => 'no-store']);
    }
}
