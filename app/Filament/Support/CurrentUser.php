<?php

namespace App\Filament\Support;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * The signed-in admin user, or a refusal. (Not assert(): assertions can be
 * switched off in production, so they must never guard access.)
 */
final class CurrentUser
{
    public static function get(): User
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            throw new AuthorizationException;
        }

        return $user;
    }
}
