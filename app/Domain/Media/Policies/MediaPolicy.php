<?php

namespace App\Domain\Media\Policies;

use App\Domain\Media\Models\Media;
use App\Models\User;

/**
 * Media library access (PLAN.md §8). Authors may upload and correct their
 * own files; editing anyone's file needs `media.update`; deleting needs
 * `media.delete` and is refused while the file is in use.
 */
final class MediaPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('media.view');
    }

    public function view(User $actor, Media $media): bool
    {
        return $actor->can('media.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('media.upload');
    }

    public function update(User $actor, Media $media): bool
    {
        if ($actor->can('media.update')) {
            return true;
        }

        return $actor->can('media.upload') && $media->uploaded_by === $actor->id;
    }

    public function delete(User $actor, Media $media): bool
    {
        return $actor->can('media.delete') && ! $media->isUsed();
    }

    public function deleteAny(User $actor): bool
    {
        return $actor->can('media.delete');
    }

    public function restore(User $actor, Media $media): bool
    {
        return false;
    }

    public function restoreAny(User $actor): bool
    {
        return false;
    }

    public function forceDelete(User $actor, Media $media): bool
    {
        return false;
    }

    public function forceDeleteAny(User $actor): bool
    {
        return false;
    }

    public function replicate(User $actor, Media $media): bool
    {
        return false;
    }

    public function reorder(User $actor): bool
    {
        return false;
    }
}
