<?php

namespace App\Domain\Content\Policies;

use App\Domain\Content\Models\Menu;
use App\Models\User;

/**
 * Menus (PLAN.md §8: publishers and admins manage menus).
 */
final class MenuPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('menus.manage');
    }

    public function view(User $actor, Menu $menu): bool
    {
        return $actor->can('menus.manage');
    }

    public function create(User $actor): bool
    {
        return $actor->can('menus.manage');
    }

    public function update(User $actor, Menu $menu): bool
    {
        return $actor->can('menus.manage');
    }

    public function delete(User $actor, Menu $menu): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function restore(User $actor, Menu $menu): bool
    {
        return false;
    }

    public function restoreAny(User $actor): bool
    {
        return false;
    }

    public function forceDelete(User $actor, Menu $menu): bool
    {
        return false;
    }

    public function forceDeleteAny(User $actor): bool
    {
        return false;
    }

    public function replicate(User $actor, Menu $menu): bool
    {
        return false;
    }

    public function reorder(User $actor): bool
    {
        return false;
    }
}
