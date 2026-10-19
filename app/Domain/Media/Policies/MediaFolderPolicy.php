<?php

namespace App\Domain\Media\Policies;

use App\Domain\Media\Models\MediaFolder;
use App\Models\User;

/**
 * Folders are organised by whoever may edit any media file; a folder can only
 * be deleted when it holds no files and no folders.
 */
final class MediaFolderPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('media.view');
    }

    public function view(User $actor, MediaFolder $folder): bool
    {
        return $actor->can('media.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('media.update');
    }

    public function update(User $actor, MediaFolder $folder): bool
    {
        return $actor->can('media.update');
    }

    public function delete(User $actor, MediaFolder $folder): bool
    {
        return $actor->can('media.delete')
            && $folder->media()->doesntExist()
            && $folder->children()->doesntExist();
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function restore(User $actor, MediaFolder $folder): bool
    {
        return false;
    }

    public function restoreAny(User $actor): bool
    {
        return false;
    }

    public function forceDelete(User $actor, MediaFolder $folder): bool
    {
        return false;
    }

    public function forceDeleteAny(User $actor): bool
    {
        return false;
    }

    public function replicate(User $actor, MediaFolder $folder): bool
    {
        return false;
    }

    public function reorder(User $actor): bool
    {
        return false;
    }
}
