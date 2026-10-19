<?php

namespace App\Http\Middleware;

use App\Domain\Translation\Support\Locales;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the language in the URL prefix (/en, /am, /om, /har), remembers it
 * in a cookie for the next visit to "/", and makes route() keep the language
 * so links stay in it (PLAN.md §3).
 */
final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $locale = $route?->parameter('locale');

        if (! is_string($locale) || ! Locales::isSupported($locale)) {
            abort(404);
        }

        app()->setLocale($locale);
        Carbon::setLocale($locale);
        URL::defaults(['locale' => $locale]);

        // Controllers never receive the locale argument; views and helpers
        // read it from app()->getLocale() and route()->originalParameters().
        $route->forgetParameter('locale');

        if ($request->cookie(Locales::COOKIE) !== $locale) {
            Cookie::queue(Cookie::make(Locales::COOKIE, $locale, 60 * 24 * 365, httpOnly: true, sameSite: 'lax'));
        }

        return $next($request);
    }
}
