<?php

namespace App\Domain\Forms\Policies;

use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Forms\Support\InboxScope;
use App\Models\User;

/**
 * The inbox (PLAN.md §8): `inbox.view` to read tickets of one's offices,
 * `inbox.reply` to answer and move them on, `inbox.assign` to hand them to
 * someone. Tickets are never created or deleted by hand.
 */
final class FormSubmissionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('inbox.view');
    }

    public function view(User $actor, FormSubmission $submission): bool
    {
        return InboxScope::covers($actor, $submission);
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, FormSubmission $submission): bool
    {
        return InboxScope::covers($actor, $submission, 'inbox.reply');
    }

    public function delete(User $actor, FormSubmission $submission): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
