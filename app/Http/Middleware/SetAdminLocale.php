<?php

namespace App\Http\Middleware;

use App\Domain\Translation\Support\Locales;
use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shows the admin panel in each staff member's preferred language
 * (PLAN.md §7: admin UI in English and Amharic at minimum); English when
 * none is chosen. Filament's own labels fall back to English.
 */
final class SetAdminLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Filament::auth()->user();
        $locale = $user instanceof User && Locales::isSupported($user->locale) ? (string) $user->locale : 'en';

        app()->setLocale($locale);

        return $next($request);
    }
}
