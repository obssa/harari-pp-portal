<?php

namespace App\Console\Commands;

use App\Domain\Access\Actions\SyncPermissions;
use Illuminate\Console\Command;

final class SyncPermissionsCommand extends Command
{
    protected $signature = 'portal:sync-permissions';

    protected $description = 'Create missing permissions and system roles (safe on every deploy; keeps Super Admin changes)';

    public function handle(SyncPermissions $sync): int
    {
        $result = $sync();

        $this->components->info(sprintf(
            'Permissions: %d created, %d removed. Roles created: %s.',
            count($result['created']),
            count($result['deleted']),
            $result['roles_created'] === [] ? 'none' : implode(', ', $result['roles_created']),
        ));

        return self::SUCCESS;
    }
}
