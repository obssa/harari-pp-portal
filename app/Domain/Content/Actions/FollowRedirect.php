<?php

namespace App\Domain\Content\Actions;

use App\Domain\Content\Models\Redirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Sends a visitor from a moved address to the new one, counting the hit
 * (ADR-0015). Returns null when the address is unknown, so the 404 page
 * is shown as usual.
 */
final class FollowRedirect
{
    public static function for(Request $request): ?RedirectResponse
    {
        try {
            $redirect = Redirect::query()->where('from_path', Redirect::normalise($request->getPathInfo()))->first();
        } catch (Throwable) {
            return null;
        }

        if ($redirect === null) {
            return null;
        }

        Redirect::query()->whereKey($redirect->id)->update(['hits' => $redirect->hits + 1, 'last_hit_at' => now()]);

        $query = (string) $request->server('QUERY_STRING');

        return redirect($redirect->to_path.($query !== '' ? '?'.$query : ''), $redirect->status)
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
