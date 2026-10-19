<?php

namespace App\Domain\Content\Policies;

use App\Domain\Content\Models\Redirect;
use App\Models\User;

/**
 * Redirects change where visitors land: publishers and admins (redirects.manage).
 */
final class RedirectPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('redirects.manage');
    }

    public function view(User $actor, Redirect $redirect): bool
    {
        return $actor->can('redirects.manage');
    }

    public function create(User $actor): bool
    {
        return $actor->can('redirects.manage');
    }

    public function update(User $actor, Redirect $redirect): bool
    {
        return $actor->can('redirects.manage');
    }

    public function delete(User $actor, Redirect $redirect): bool
    {
        return $actor->can('redirects.manage');
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function restore(User $actor, Redirect $redirect): bool
    {
        return false;
    }

    public function restoreAny(User $actor): bool
    {
        return false;
    }

    public function forceDelete(User $actor, Redirect $redirect): bool
    {
        return false;
    }

    public function forceDeleteAny(User $actor): bool
    {
        return false;
    }

    public function replicate(User $actor, Redirect $redirect): bool
    {
        return false;
    }

    public function reorder(User $actor): bool
    {
        return false;
    }
}
