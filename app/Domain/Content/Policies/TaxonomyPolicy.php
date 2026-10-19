<?php

namespace App\Domain\Content\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Categories and tags: everyone who works on posts may see them; changing
 * them needs `taxonomies.manage` (Publisher, Editor, Admin). Deleting is
 * soft and can be undone.
 */
final class TaxonomyPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('taxonomies.manage') || $actor->can('posts.view');
    }

    public function view(User $actor, Model $record): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $actor->can('taxonomies.manage');
    }

    public function update(User $actor, Model $record): bool
    {
        return $actor->can('taxonomies.manage');
    }

    public function delete(User $actor, Model $record): bool
    {
        return $actor->can('taxonomies.manage');
    }

    public function deleteAny(User $actor): bool
    {
        return $actor->can('taxonomies.manage');
    }

    public function restore(User $actor, Model $record): bool
    {
        return $actor->can('taxonomies.manage');
    }

    public function restoreAny(User $actor): bool
    {
        return $actor->can('taxonomies.manage');
    }

    public function forceDelete(User $actor, Model $record): bool
    {
        return false;
    }

    public function forceDeleteAny(User $actor): bool
    {
        return false;
    }

    public function replicate(User $actor, Model $record): bool
    {
        return false;
    }

    public function reorder(User $actor): bool
    {
        return $actor->can('taxonomies.manage');
    }
}
