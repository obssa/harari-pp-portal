<?php

namespace App\Domain\Access\Actions;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Enums\SystemRole;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\RoleScope;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Applies the roles and scopes chosen for a user, on behalf of an actor.
 *
 * - The actor can only grant or remove roles they may assign (UserPolicy::assignRole);
 *   roles outside their reach that the user already holds are kept untouched.
 * - Roles change through the permission package so its events fire (audit + sign-out).
 * - Scopes are only kept for roles the user ends up with, and only of an allowed type.
 */
final class UpdateUserAccess
{
    /**
     * @param  list<string>  $roleNames
     * @param  list<array{role?: mixed, scope_type?: mixed, scope_value?: mixed}>  $scopes
     */
    public function __invoke(User $actor, User $user, array $roleNames, array $scopes): void
    {
        $assignable = Role::query()->pluck('name')
            ->filter(fn (string $name): bool => $actor->can('assignRole', [User::class, $name]))
            ->values()->all();

        $current = $user->roles()->pluck('name')->all();
        $final = array_values(array_unique([
            ...array_intersect($roleNames, $assignable),
            ...array_diff($current, $assignable),
        ]));
        sort($final);
        $sortedCurrent = $current;
        sort($sortedCurrent);

        DB::transaction(function () use ($user, $final, $sortedCurrent, $scopes): void {
            if ($final !== $sortedCurrent) {
                $user->syncRoles($final);
            }

            $this->syncScopes($user->fresh() ?? $user, $final, $scopes);
        });
    }

    /**
     * @param  list<string>  $roles
     * @param  list<array{role?: mixed, scope_type?: mixed, scope_value?: mixed}>  $rows
     */
    private function syncScopes(User $user, array $roles, array $rows): void
    {
        $roleIds = Role::query()->whereIn('name', $roles)->pluck('id', 'name');
        $wanted = [];

        foreach ($rows as $row) {
            $role = (string) ($row['role'] ?? '');
            $type = ScopeType::tryFrom((string) ($row['scope_type'] ?? ''));
            $value = trim((string) ($row['scope_value'] ?? ''));
            $system = SystemRole::tryFrom($role);

            if (! isset($roleIds[$role]) || $type === null || $value === '' || mb_strlen($value) > 64) {
                continue;
            }
            if ($system !== null && ! in_array($type, $system->allowedScopes(), true)) {
                continue;
            }

            $wanted[$roleIds[$role].'|'.$type->value.'|'.$value] = [(int) $roleIds[$role], $type, $value];
        }

        $user->roleScopes()->get()->each(function (RoleScope $scope) use (&$wanted): void {
            $key = $scope->role_id.'|'.$scope->scope_type->value.'|'.$scope->scope_value;
            if (isset($wanted[$key])) {
                unset($wanted[$key]);

                return;
            }
            $scope->delete();
        });

        foreach ($wanted as [$roleId, $type, $value]) {
            $user->roleScopes()->create([
                'role_id' => $roleId,
                'scope_type' => $type->value,
                'scope_value' => $value,
            ]);
        }

        $user->unsetRelation('roleScopes');
    }
}
