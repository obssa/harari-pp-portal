<?php

namespace App\Http\Middleware;

use App\Domain\Translation\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * The language of an API answer (ADR-0056): `?lang=`, otherwise the
 * Accept-Language header, otherwise the site's default language.
 */
final class SetApiLanguage
{
    public function handle(Request $request, Closure $next): Response
    {
        $asked = $request->query('lang');
        if ($asked !== null && ! Locales::isSupported(is_string($asked) ? $asked : null)) {
            return response()->json([
                'message' => __('api.error.language', ['languages' => implode(', ', Locales::all())]),
            ], 422);
        }

        $locale = is_string($asked) ? $asked : (Locales::fromAcceptLanguage($request->headers->get('Accept-Language')) ?? Locales::default());
        app()->setLocale($locale);
        URL::defaults(['locale' => $locale]);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }
}
