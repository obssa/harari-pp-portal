<?php

namespace App\Domain\Forms\Policies;

use App\Domain\Forms\Models\Form;
use App\Models\User;

/**
 * Forms (PLAN.md §7 forms builder): `forms.manage` to see, build, publish
 * and remove them. What people send is handled in the inbox.
 */
final class FormPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('forms.manage');
    }

    public function view(User $actor, Form $form): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $actor->can('forms.manage');
    }

    public function update(User $actor, Form $form): bool
    {
        return $actor->can('forms.manage');
    }

    public function delete(User $actor, Form $form): bool
    {
        return $actor->can('forms.manage');
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function restore(User $actor, Form $form): bool
    {
        return $actor->can('forms.manage');
    }

    public function restoreAny(User $actor): bool
    {
        return false;
    }

    public function forceDelete(User $actor, Form $form): bool
    {
        return false;
    }

    public function forceDeleteAny(User $actor): bool
    {
        return false;
    }

    public function replicate(User $actor, Form $form): bool
    {
        return false;
    }

    public function reorder(User $actor): bool
    {
        return $actor->can('forms.manage');
    }
}
