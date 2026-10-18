<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Auth\MultiFactor\Http\Middleware\EnsureMultiFactorAuthenticationIsEnabled;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Super Admins, Admins and Publishers must turn on an authenticator app
 * before using the panel (PLAN.md §7, config portal.mfa_required_roles).
 * Other staff may turn it on voluntarily from their profile.
 */
final class RequireMfaForPrivilegedRoles
{
    public function __construct(private readonly EnsureMultiFactorAuthenticationIsEnabled $filament) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User || ! $user->requiresMfa()) {
            return $next($request);
        }

        /** @var Response */
        return $this->filament->handle($request, $next);
    }
}
