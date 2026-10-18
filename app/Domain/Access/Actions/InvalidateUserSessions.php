<?php

namespace App\Domain\Access\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Signs a user out everywhere: deletes their stored sessions and rotates the
 * remember-me token (PLAN.md §11: session invalidation on role change).
 */
final class InvalidateUserSessions
{
    public function __invoke(User $user): int
    {
        $deleted = 0;
        if (config('session.driver') === 'database') {
            $deleted = DB::table((string) config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->delete();
        }

        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

        return $deleted;
    }
}
