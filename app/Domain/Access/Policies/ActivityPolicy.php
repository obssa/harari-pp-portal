<?php

namespace App\Domain\Access\Policies;

use App\Models\User;
use Spatie\Activitylog\Models\Activity;

/**
 * The audit log is read-only for everyone; purging is a Super Admin
 * maintenance task outside the panel (PLAN.md §7: immutable audit log).
 */
final class ActivityPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('activity.view');
    }

    public function view(User $actor, Activity $activity): bool
    {
        return $actor->can('activity.view');
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, Activity $activity): bool
    {
        return false;
    }

    public function delete(User $actor, Activity $activity): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function restore(User $actor, Activity $activity): bool
    {
        return false;
    }

    public function restoreAny(User $actor): bool
    {
        return false;
    }

    public function forceDelete(User $actor, Activity $activity): bool
    {
        return false;
    }

    public function forceDeleteAny(User $actor): bool
    {
        return false;
    }

    public function replicate(User $actor, Activity $activity): bool
    {
        return false;
    }

    public function reorder(User $actor): bool
    {
        return false;
    }
}
