<?php

namespace App\Domain\Forms\Support;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Models\RoleScope;
use App\Domain\Forms\Models\FormSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

/**
 * Which tickets a person may handle (PLAN.md §8: support agents and office
 * heads per office, ADR-0028). A role held without a scope covers every
 * office; a role scoped to offices covers those offices only. Tickets of no
 * office belong to staff without a scope.
 */
final class InboxScope
{
    /**
     * @return list<string>|null null for every office
     */
    public static function officeIds(User $user, string $permission = 'inbox.view'): ?array
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
     * @param  Builder<FormSubmission>  $query
     * @return Builder<FormSubmission>
     */
    public static function apply(Builder $query, User $user, string $permission = 'inbox.view'): Builder
    {
        $offices = self::officeIds($user, $permission);

        return $offices === null ? $query : $query->whereIn('office_id', $offices);
    }

    public static function covers(User $user, FormSubmission $submission, string $permission = 'inbox.view'): bool
    {
        if (! $user->can($permission)) {
            return false;
        }
        $offices = self::officeIds($user, $permission);

        return $offices === null || ($submission->office_id !== null && in_array($submission->office_id, $offices, true));
    }
}
