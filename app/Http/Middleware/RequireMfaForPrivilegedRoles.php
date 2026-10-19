<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Auth\MultiFactor\Http\Middleware\EnsureMultiFactorAuthenticationIsEnabled;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While a Super Admin has made two-step sign-in compulsory (Settings →
 * Security, ADR-0018), Super Admins, Admins and Publishers must turn on an
 * authenticator app before using the panel (config portal.mfa_required_roles).
 * Anyone may turn it on for their own account from their profile.
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
