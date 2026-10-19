<?php

namespace App\Domain\Organisation\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Offices, leaders and leagues (PLAN.md §8): `{module}.view` to see them in
 * the admin, `{module}.manage` to change, publish and remove them.
 */
abstract class DirectoryPolicy
{
    abstract protected function module(): string;

    public function viewAny(User $actor): bool
    {
        return $actor->can($this->module().'.view') || $actor->can($this->module().'.manage');
    }

    public function view(User $actor, Model $record): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $actor->can($this->module().'.manage');
    }

    public function update(User $actor, Model $record): bool
    {
        return $actor->can($this->module().'.manage');
    }

    public function delete(User $actor, Model $record): bool
    {
        return $actor->can($this->module().'.manage');
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function restore(User $actor, Model $record): bool
    {
        return $actor->can($this->module().'.manage');
    }

    public function restoreAny(User $actor): bool
    {
        return false;
    }

    public function forceDelete(User $actor, Model $record): bool
    {
        return false;
    }

    public function forceDeleteAny(User $actor): bool
    {
        return false;
    }

    public function replicate(User $actor, Model $record): bool
    {
        return false;
    }

    public function reorder(User $actor): bool
    {
        return $actor->can($this->module().'.manage');
    }
}
