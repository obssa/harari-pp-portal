<?php

namespace App\Http\Controllers\Public;

use App\Domain\Translation\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "/" sends visitors to their language: the remembered cookie, then the
 * browser's Accept-Language, then the site default (PLAN.md §3).
 */
final class RedirectToPreferredLocale
{
    public function __invoke(Request $request): RedirectResponse
    {
        $cookie = $request->cookie(Locales::COOKIE);

        $locale = is_string($cookie) && Locales::isSupported($cookie)
            ? $cookie
            : (Locales::fromAcceptLanguage($request->header('Accept-Language')) ?? Locales::default());

        // The visitor's own query string, unsorted (campaign links stay recognisable).
        $query = (string) $request->server('QUERY_STRING');

        return redirect('/'.$locale.($query !== '' ? '?'.$query : ''), 302)
            // The answer depends on who asks: never let a cache share it.
            ->header('Cache-Control', 'private, no-store')
            ->header('Vary', 'Accept-Language, Cookie');
    }
}
