<?php

namespace App\Domain\Access\Actions;

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Access\Models\Permission;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Support\PermissionRegistry;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Brings the database in line with PermissionRegistry and SystemRole.
 *
 * Safe to run on every deploy:
 *  - creates missing permissions and deletes ones no longer in the registry;
 *  - creates missing system roles with their default grants;
 *  - grants a NEW permission to the system roles whose defaults include it;
 *  - never removes grants a Super Admin changed on existing roles,
 *    except that the Super Admin role always holds every permission.
 */
final class SyncPermissions
{
    /**
     * @return array{created: list<string>, deleted: list<string>, roles_created: list<string>}
     */
    public function __invoke(): array
    {
        $guard = 'web';
        $wanted = PermissionRegistry::all();

        return DB::transaction(function () use ($guard, $wanted): array {
            $existing = Permission::query()->where('guard_name', $guard)->pluck('name')->all();

            $created = array_values(array_diff($wanted, $existing));
            foreach ($created as $name) {
                Permission::query()->create(['name' => $name, 'guard_name' => $guard]);
            }

            $deleted = array_values(array_diff($existing, $wanted));
            if ($deleted !== []) {
                Permission::query()->where('guard_name', $guard)->whereIn('name', $deleted)->delete();
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $rolesCreated = [];
            foreach (SystemRole::cases() as $system) {
                /** @var Role|null $role */
                $role = Role::query()->where('name', $system->value)->where('guard_name', $guard)->first();

                if ($role === null) {
                    $role = Role::query()->create(['name' => $system->value, 'guard_name' => $guard]);
                    $role->syncPermissions($system->defaultPermissions());
                    $rolesCreated[] = $system->value;

                    continue;
                }

                if ($system === SystemRole::SuperAdmin) {
                    $role->syncPermissions($wanted);

                    continue;
                }

                $newDefaults = array_values(array_intersect($created, $system->defaultPermissions()));
                if ($newDefaults !== []) {
                    $role->givePermissionTo($newDefaults);
                }
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return ['created' => $created, 'deleted' => $deleted, 'roles_created' => $rolesCreated];
        });
    }
}
