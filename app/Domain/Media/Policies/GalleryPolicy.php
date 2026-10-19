<?php

namespace App\Domain\Media\Policies;

use App\Domain\Media\Models\Gallery;
use App\Models\User;

/**
 * Photo albums (PLAN.md §8): those who manage galleries create, publish and
 * remove them.
 */
final class GalleryPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('galleries.manage');
    }

    public function view(User $actor, Gallery $gallery): bool
    {
        return $actor->can('galleries.manage');
    }

    public function create(User $actor): bool
    {
        return $actor->can('galleries.manage');
    }

    public function update(User $actor, Gallery $gallery): bool
    {
        return $actor->can('galleries.manage');
    }

    public function delete(User $actor, Gallery $gallery): bool
    {
        return $actor->can('galleries.manage');
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function restore(User $actor, Gallery $gallery): bool
    {
        return $actor->can('galleries.manage');
    }

    public function restoreAny(User $actor): bool
    {
        return false;
    }

    public function forceDelete(User $actor, Gallery $gallery): bool
    {
        return false;
    }

    public function forceDeleteAny(User $actor): bool
    {
        return false;
    }

    public function replicate(User $actor, Gallery $gallery): bool
    {
        return false;
    }

    public function reorder(User $actor): bool
    {
        return $actor->can('galleries.manage');
    }
}
