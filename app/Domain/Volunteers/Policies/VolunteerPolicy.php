<?php

namespace App\Domain\Volunteers\Policies;

use App\Domain\Access\Support\OfficeScope;
use App\Domain\Volunteers\Models\Volunteer;
use App\Models\User;

/**
 * Volunteers are seen by those with `volunteers.view` and managed by those
 * with `volunteers.manage`, for the woredas they cover (ADR-0048); they are
 * removed through privacy requests, not a delete button.
 */
final class VolunteerPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('volunteers.view');
    }

    public function view(User $actor, Volunteer $volunteer): bool
    {
        return OfficeScope::covers($actor, $volunteer->woreda_id, 'volunteers.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('volunteers.manage');
    }

    public function update(User $actor, Volunteer $volunteer): bool
    {
        return OfficeScope::covers($actor, $volunteer->woreda_id, 'volunteers.manage');
    }

    public function delete(User $actor, Volunteer $volunteer): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
