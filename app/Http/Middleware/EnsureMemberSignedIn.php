<?php

namespace App\Http\Middleware;

use App\Domain\Membership\Models\Member;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Member portal pages (ADR-0049): a signed-in member who may still use the
 * portal (active or lapsed, not erased); otherwise back to the sign-in page.
 * Sessions end after 30 minutes without activity.
 */
final class EnsureMemberSignedIn
{
    public const IDLE_MINUTES = 30;

    private const LAST_SEEN = 'member.last_seen';

    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('member');
        $member = $guard->user();
        $lastSeen = (int) $request->session()->get(self::LAST_SEEN, 0);

        if (! $member instanceof Member || ! $member->canUsePortal() || ($lastSeen > 0 && time() - $lastSeen > self::IDLE_MINUTES * 60)) {
            $guard->logout();
            $request->session()->forget(self::LAST_SEEN);

            return redirect()->route('member.sign-in');
        }
        $request->session()->put(self::LAST_SEEN, time());

        $response = $next($request);
        // Private pages: never in search results.
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
