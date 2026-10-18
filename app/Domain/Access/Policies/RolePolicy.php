<?php

namespace App\Domain\Access\Policies;

use App\Domain\Access\Models\Role;
use App\Models\User;

/**
 * The Super Admin role is immutable (it always holds every permission) and
 * system roles cannot be deleted.
 */
final class RolePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('roles.view');
    }

    public function view(User $actor, Role $role): bool
    {
        return $actor->can('roles.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('roles.create');
    }

    public function update(User $actor, Role $role): bool
    {
        return $actor->can('roles.update') && ! $role->isSuperAdmin();
    }

    public function delete(User $actor, Role $role): bool
    {
        return $actor->can('roles.delete') && ! $role->isSystem() && $role->users()->doesntExist();
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function restore(User $actor, Role $role): bool
    {
        return false;
    }

    public function restoreAny(User $actor): bool
    {
        return false;
    }

    public function forceDelete(User $actor, Role $role): bool
    {
        return false;
    }

    public function forceDeleteAny(User $actor): bool
    {
        return false;
    }

    public function replicate(User $actor, Role $role): bool
    {
        return false;
    }

    public function reorder(User $actor): bool
    {
        return false;
    }
}
