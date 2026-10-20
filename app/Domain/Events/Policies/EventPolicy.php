<?php

namespace App\Domain\Events\Policies;

use App\Domain\Events\Models\Event;
use App\Models\User;

/**
 * Events (PLAN.md §8): `events.view` to see them in the admin,
 * `events.manage` to create, publish and remove them; `events.registrations`
 * also opens them, for the registrations (ADR-0025).
 */
final class EventPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('events.view') || $actor->can('events.manage') || $actor->can('events.registrations');
    }

    public function view(User $actor, Event $event): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $actor->can('events.manage');
    }

    public function update(User $actor, Event $event): bool
    {
        return $actor->can('events.manage');
    }

    public function delete(User $actor, Event $event): bool
    {
        return $actor->can('events.manage');
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function restore(User $actor, Event $event): bool
    {
        return $actor->can('events.manage');
    }

    public function restoreAny(User $actor): bool
    {
        return false;
    }

    public function forceDelete(User $actor, Event $event): bool
    {
        return false;
    }

    public function forceDeleteAny(User $actor): bool
    {
        return false;
    }

    public function replicate(User $actor, Event $event): bool
    {
        return false;
    }

    public function reorder(User $actor): bool
    {
        return $actor->can('events.manage');
    }
}
