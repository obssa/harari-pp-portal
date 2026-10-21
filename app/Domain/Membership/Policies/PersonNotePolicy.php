<?php

namespace App\Domain\Membership\Policies;

use App\Domain\Membership\Models\PersonNote;
use App\Models\User;

/**
 * The history of an application or a member is seen with it and written
 * only by the review actions, never edited or deleted.
 */
final class PersonNotePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('applications.view') || $actor->can('members.view');
    }

    public function view(User $actor, PersonNote $note): bool
    {
        return $note->notable !== null && $actor->can('view', $note->notable);
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, PersonNote $note): bool
    {
        return false;
    }

    public function delete(User $actor, PersonNote $note): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
