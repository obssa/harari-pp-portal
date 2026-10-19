<?php

namespace App\Domain\Content\Policies;

use App\Domain\Content\Models\Revision;
use App\Models\User;

/**
 * Versions are read by whoever may read the content; they are never edited
 * or deleted by hand (restoring is an update of the record itself).
 */
final class RevisionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('posts.view') || $actor->can('pages.view');
    }

    public function view(User $actor, Revision $revision): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, Revision $revision): bool
    {
        return false;
    }

    public function delete(User $actor, Revision $revision): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function restore(User $actor, Revision $revision): bool
    {
        return false;
    }

    public function restoreAny(User $actor): bool
    {
        return false;
    }

    public function forceDelete(User $actor, Revision $revision): bool
    {
        return false;
    }

    public function forceDeleteAny(User $actor): bool
    {
        return false;
    }

    public function replicate(User $actor, Revision $revision): bool
    {
        return false;
    }

    public function reorder(User $actor): bool
    {
        return false;
    }

    public function associate(User $actor, Revision $revision): bool
    {
        return false;
    }

    public function dissociate(User $actor, Revision $revision): bool
    {
        return false;
    }

    public function attach(User $actor, Revision $revision): bool
    {
        return false;
    }

    public function detach(User $actor, Revision $revision): bool
    {
        return false;
    }
}
