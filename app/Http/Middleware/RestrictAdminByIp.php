<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional allow-list for the admin (PLAN.md §11, ADR-0038): when
 * PORTAL_ADMIN_ALLOWED_IPS lists addresses or ranges (CIDR), the admin,
 * including its sign-in page and Livewire requests, answers 403 to every
 * other address. Empty: open to all, protected by passwords and two-step
 * sign-in as before. The client address is the one the web server takes
 * from Cloudflare.
 */
final class RestrictAdminByIp
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = self::allowed();
        if ($allowed !== [] && ! IpUtils::checkIp((string) $request->ip(), $allowed)) {
            abort(403);
        }

        return $next($request);
    }

    /**
     * @return list<string>
     */
    public static function allowed(): array
    {
        $configured = config('portal.admin_allowed_ips', []);
        $items = is_array($configured) ? $configured : explode(',', (string) $configured);

        return array_values(array_filter(array_map(fn (mixed $item): string => trim((string) $item), $items)));
    }
}
