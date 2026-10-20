<?php

namespace App\Domain\Documents\Policies;

use App\Domain\Documents\Models\Document;
use App\Models\User;

/**
 * Documents (PLAN.md §8): `documents.view` to see them in the admin,
 * `documents.manage` to create, publish, replace files and remove them.
 */
final class DocumentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('documents.view') || $actor->can('documents.manage');
    }

    public function view(User $actor, Document $document): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $actor->can('documents.manage');
    }

    public function update(User $actor, Document $document): bool
    {
        return $actor->can('documents.manage');
    }

    public function delete(User $actor, Document $document): bool
    {
        return $actor->can('documents.manage');
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function restore(User $actor, Document $document): bool
    {
        return $actor->can('documents.manage');
    }

    public function restoreAny(User $actor): bool
    {
        return false;
    }

    public function forceDelete(User $actor, Document $document): bool
    {
        return false;
    }

    public function forceDeleteAny(User $actor): bool
    {
        return false;
    }

    public function replicate(User $actor, Document $document): bool
    {
        return false;
    }

    public function reorder(User $actor): bool
    {
        return $actor->can('documents.manage');
    }
}
