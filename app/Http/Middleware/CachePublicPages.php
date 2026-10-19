<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets Cloudflare keep public pages for a few minutes (PLAN.md §9 caching,
 * ADR-0010). Only anonymous, successful GET responses that set no cookie are
 * shared; browsers always revalidate. Public pages run without a session, so
 * the usual answer is cacheable; the first visit in a new language sets the
 * language cookie and stays private.
 */
final class CachePublicPages
{
    public const EDGE_SECONDS = 600;

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $shareable = $request->isMethod('GET')
            && $response->getStatusCode() === 200
            && $response->headers->getCookies() === []
            && Cookie::getQueuedCookies() === []
            && ! $request->hasSession()
            && ! $request->headers->has('Authorization');

        $response->headers->set('Cache-Control', $shareable
            ? 'public, max-age=0, s-maxage='.self::EDGE_SECONDS.', stale-while-revalidate=60'
            : 'private, no-cache');

        return $response;
    }
}
