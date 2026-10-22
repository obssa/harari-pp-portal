<?php

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;

/*
 * The web server answers a request for an existing folder under public/
 * itself, before Laravel sees it (a public/admin folder made /admin a 403).
 * So no folder may share its name with the first segment of a route.
 */
it('has no public folder named like a route', function () {
    $prefixes = collect(Router::getRoutes()->getRoutes())
        ->map(fn (Route $route): string => explode('/', trim($route->uri(), '/'))[0])
        ->reject(fn (string $segment): bool => $segment === '' || str_starts_with($segment, '{'))
        // Laravel serves the public disk under /storage on purpose; the folder is its link.
        ->reject(fn (string $segment): bool => $segment === 'storage')
        ->unique();

    $clashes = $prefixes->filter(fn (string $segment): bool => is_dir(public_path($segment)))->values()->all();

    expect($clashes)->toBe([]);
});
