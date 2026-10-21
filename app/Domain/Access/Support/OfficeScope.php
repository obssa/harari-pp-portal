<?php

namespace App\Domain\Access\Support;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Models\RoleScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

/**
 * Which offices a person may act for with a permission (PLAN.md §8: office-
 * scoped roles such as Membership Officer per woreda; ADR-0006, ADR-0045).
 * A role held without a scope covers every office; a role scoped to offices
 * covers those only; records without an office belong to unscoped staff.
 */
final class OfficeScope
{
    /**
     * @return list<string>|null null for every office
     */
    public static function officeIds(User $user, string $permission): ?array
    {
        $user->loadMissing(['roles', 'roleScopes']);
        if ($user->isSuperAdmin() || $user->hasDirectPermission($permission)) {
            return null;
        }

        $offices = [];
        foreach ($user->roles as $role) {
            if (! $role instanceof Role || ! $role->hasPermissionTo($permission)) {
                continue;
            }
            $scopes = $user->roleScopes->where('role_id', $role->getKey());
            if ($scopes->isEmpty()) {
                return null;
            }
            foreach ($scopes as $scope) {
                /** @var RoleScope $scope */
                if ($scope->scope_type === ScopeType::Office) {
                    $offices[] = $scope->scope_value;
                }
            }
        }

        return array_values(array_unique($offices));
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function apply(Builder $query, User $user, string $permission, string $column = 'woreda_id'): Builder
    {
        $offices = self::officeIds($user, $permission);

        return $offices === null ? $query : $query->whereIn($query->getModel()->qualifyColumn($column), $offices);
    }

    public static function covers(User $user, ?string $officeId, string $permission): bool
    {
        if (! $user->can($permission)) {
            return false;
        }
        $offices = self::officeIds($user, $permission);

        return $offices === null || ($officeId !== null && in_array($officeId, $offices, true));
    }
}
