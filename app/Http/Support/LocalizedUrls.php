<?php

namespace App\Http\Support;

use App\Domain\Translation\Support\Locales;
use Illuminate\Routing\Route;

/**
 * The current page in every language (hreflang links and the switcher).
 * Pages whose slug differs per language pass their own URLs instead.
 */
final class LocalizedUrls
{
    /**
     * @return array<string, string> locale => absolute URL
     */
    public static function forCurrentRoute(): array
    {
        $route = request()->route();
        if (! $route instanceof Route || $route->getName() === null) {
            return [];
        }

        // Only parameters that are part of the URL: Route::view() and route
        // defaults would otherwise leak into the links as a query string.
        $parameters = array_intersect_key($route->originalParameters(), array_flip($route->parameterNames()));
        if (! array_key_exists('locale', $parameters)) {
            return [];
        }

        $urls = [];
        foreach (Locales::all() as $locale) {
            $urls[$locale] = route($route->getName(), [...$parameters, 'locale' => $locale]);
        }

        return $urls;
    }
}
