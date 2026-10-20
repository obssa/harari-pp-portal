<?php

namespace App\Domain\Projects\Policies;

use App\Domain\Projects\Models\Statistic;
use App\Models\User;

/**
 * Statistics belong with projects (ADR-0027): `projects.view` to see them,
 * `projects.manage` to change them.
 */
final class StatisticPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('projects.view') || $actor->can('projects.manage');
    }

    public function view(User $actor, Statistic $statistic): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $actor->can('projects.manage');
    }

    public function update(User $actor, Statistic $statistic): bool
    {
        return $actor->can('projects.manage');
    }

    public function delete(User $actor, Statistic $statistic): bool
    {
        return $actor->can('projects.manage');
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function restore(User $actor, Statistic $statistic): bool
    {
        return $actor->can('projects.manage');
    }

    public function restoreAny(User $actor): bool
    {
        return false;
    }

    public function forceDelete(User $actor, Statistic $statistic): bool
    {
        return false;
    }

    public function forceDeleteAny(User $actor): bool
    {
        return false;
    }

    public function replicate(User $actor, Statistic $statistic): bool
    {
        return false;
    }

    public function reorder(User $actor): bool
    {
        return $actor->can('projects.manage');
    }
}
