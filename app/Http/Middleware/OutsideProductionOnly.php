<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hides developer pages such as /styleguide in production (answers 404).
 */
final class OutsideProductionOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(app()->isProduction(), 404);

        return $next($request);
    }
}
