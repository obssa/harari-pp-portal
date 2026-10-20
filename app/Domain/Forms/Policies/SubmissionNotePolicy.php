<?php

namespace App\Domain\Forms\Policies;

use App\Domain\Forms\Models\SubmissionNote;
use App\Models\User;

/**
 * A ticket's history is seen with the ticket (`inbox.view`) and written
 * only through the inbox actions, never edited or deleted.
 */
final class SubmissionNotePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('inbox.view');
    }

    public function view(User $actor, SubmissionNote $note): bool
    {
        return $actor->can('view', $note->submission);
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, SubmissionNote $note): bool
    {
        return false;
    }

    public function delete(User $actor, SubmissionNote $note): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
