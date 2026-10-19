<?php

namespace App\Domain\Search\Policies;

use App\Domain\Search\Models\SearchTerm;
use App\Models\User;

/**
 * Search insights (PLAN.md §7, §8): read by those who see analytics.
 */
final class SearchTermPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('analytics.view');
    }

    public function view(User $actor, SearchTerm $term): bool
    {
        return $actor->can('analytics.view');
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, SearchTerm $term): bool
    {
        return false;
    }

    public function delete(User $actor, SearchTerm $term): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
