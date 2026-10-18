<?php

namespace App\Domain\Access\Policies;

use App\Domain\Access\Enums\SystemRole;
use App\Models\User;

/**
 * Deny by default. Nobody but a Super Admin may change a Super Admin, and
 * nobody may delete their own account or the last Super Admin.
 */
final class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('users.view');
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->can('users.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('users.create');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->can('users.update') && $this->mayManage($actor, $target);
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->can('users.delete')
            && $actor->isNot($target)
            && $this->mayManage($actor, $target)
            && ! $this->isLastSuperAdmin($target);
    }

    public function deleteAny(User $actor): bool
    {
        return $actor->can('users.delete');
    }

    public function restore(User $actor, User $target): bool
    {
        return $actor->can('users.delete') && $this->mayManage($actor, $target);
    }

    public function restoreAny(User $actor): bool
    {
        return $actor->can('users.delete');
    }

    /** Accounts are never purged: the audit trail must keep its actors. */
    public function forceDelete(User $actor, User $target): bool
    {
        return false;
    }

    public function forceDeleteAny(User $actor): bool
    {
        return false;
    }

    public function replicate(User $actor, User $target): bool
    {
        return false;
    }

    public function reorder(User $actor): bool
    {
        return false;
    }

    public function assignRoles(User $actor, User $target): bool
    {
        return $actor->can('users.assign_roles') && $this->mayManage($actor, $target);
    }

    public function assignRole(User $actor, string $role): bool
    {
        if (! $actor->can('users.assign_roles')) {
            return false;
        }

        return $role !== SystemRole::SuperAdmin->value || $actor->isSuperAdmin();
    }

    public function resetMfa(User $actor, User $target): bool
    {
        return $actor->can('users.reset_mfa') && $actor->isNot($target) && $this->mayManage($actor, $target);
    }

    private function mayManage(User $actor, User $target): bool
    {
        return ! $target->isSuperAdmin() || $actor->isSuperAdmin();
    }

    private function isLastSuperAdmin(User $target): bool
    {
        return $target->isSuperAdmin()
            && User::role(SystemRole::SuperAdmin->value)->whereKeyNot($target->getKey())->doesntExist();
    }
}
