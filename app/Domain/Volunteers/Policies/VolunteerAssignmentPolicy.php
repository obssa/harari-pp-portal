<?php

namespace App\Domain\Volunteers\Policies;

use App\Domain\Volunteers\Models\VolunteerAssignment;
use App\Models\User;

/**
 * Assignments follow their volunteer (ADR-0048): seen with the volunteer,
 * planned and updated by those who manage them; cancelled, never deleted.
 */
final class VolunteerAssignmentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('volunteers.view');
    }

    public function view(User $actor, VolunteerAssignment $assignment): bool
    {
        return $actor->can('view', $assignment->volunteer);
    }

    public function create(User $actor): bool
    {
        return $actor->can('volunteers.manage');
    }

    public function update(User $actor, VolunteerAssignment $assignment): bool
    {
        return $actor->can('update', $assignment->volunteer);
    }

    public function delete(User $actor, VolunteerAssignment $assignment): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
