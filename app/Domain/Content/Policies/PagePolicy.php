<?php

namespace App\Domain\Content\Policies;

use App\Domain\Content\Models\Page;
use App\Models\User;

/**
 * Pages (PLAN.md §8): editors and translators change them, publishers publish.
 */
final class PagePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('pages.view');
    }

    public function view(User $actor, Page $page): bool
    {
        return $actor->can('pages.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('pages.create');
    }

    public function update(User $actor, Page $page): bool
    {
        return $actor->can('pages.update') || $actor->can('pages.translate');
    }

    public function delete(User $actor, Page $page): bool
    {
        return $actor->can('pages.delete') && $page->children()->doesntExist();
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function restore(User $actor, Page $page): bool
    {
        return $actor->can('pages.delete');
    }

    public function restoreAny(User $actor): bool
    {
        return false;
    }

    public function forceDelete(User $actor, Page $page): bool
    {
        return false;
    }

    public function forceDeleteAny(User $actor): bool
    {
        return false;
    }

    public function replicate(User $actor, Page $page): bool
    {
        return false;
    }

    public function reorder(User $actor): bool
    {
        return $actor->can('pages.update');
    }
}
