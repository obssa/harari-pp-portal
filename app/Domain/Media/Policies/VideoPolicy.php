<?php

namespace App\Domain\Media\Policies;

use App\Domain\Media\Models\Video;
use App\Models\User;

/**
 * Videos and live broadcasts (PLAN.md §8): those who manage videos add,
 * publish and remove them.
 */
final class VideoPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('videos.manage');
    }

    public function view(User $actor, Video $video): bool
    {
        return $actor->can('videos.manage');
    }

    public function create(User $actor): bool
    {
        return $actor->can('videos.manage');
    }

    public function update(User $actor, Video $video): bool
    {
        return $actor->can('videos.manage');
    }

    public function delete(User $actor, Video $video): bool
    {
        return $actor->can('videos.manage');
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function restore(User $actor, Video $video): bool
    {
        return $actor->can('videos.manage');
    }

    public function restoreAny(User $actor): bool
    {
        return false;
    }

    public function forceDelete(User $actor, Video $video): bool
    {
        return false;
    }

    public function forceDeleteAny(User $actor): bool
    {
        return false;
    }

    public function replicate(User $actor, Video $video): bool
    {
        return false;
    }

    public function reorder(User $actor): bool
    {
        return $actor->can('videos.manage');
    }
}
