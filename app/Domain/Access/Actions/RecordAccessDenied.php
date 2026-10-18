<?php

namespace App\Domain\Access\Actions;

use Illuminate\Http\Request;

/**
 * Logs every 403 so refused attempts are visible in the audit log
 * (PLAN.md §13: denied actions return 403 and are logged).
 */
final class RecordAccessDenied
{
    public function __invoke(Request $request): void
    {
        $logger = activity('security')->event('access_denied')->withProperties([
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'route' => $request->route()?->getName(),
        ]);

        $user = $request->user();
        if ($user !== null) {
            $logger->causedBy($user);
        }

        $logger->log('access_denied');
    }
}
