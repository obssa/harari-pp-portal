<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ETags and short private caching for API answers (ADR-0056): an app that
 * sends If-None-Match with the ETag it has gets 304 without a body. Answers
 * depend on the key, so shared caches such as Cloudflare never keep them.
 */
final class ApiCacheHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethodCacheable() || $response->getStatusCode() !== 200) {
            return $response;
        }

        $response->setEtag(hash('xxh128', (string) $response->getContent()));
        $response->setPrivate();
        $response->setMaxAge((int) config('portal.api.max_age'));
        $response->headers->set('Vary', 'X-Api-Key, Accept-Language');
        $response->isNotModified($request);

        return $response;
    }
}
