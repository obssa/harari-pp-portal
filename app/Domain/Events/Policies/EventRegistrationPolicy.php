<?php

namespace App\Domain\Events\Policies;

use App\Domain\Events\Models\EventRegistration;
use App\Models\User;

/**
 * Registrations hold personal details (ADR-0025): only staff with
 * `events.registrations` see them, check people in or cancel a place.
 * Nobody edits or deletes them by hand; old ones are purged on a schedule.
 */
final class EventRegistrationPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('events.registrations');
    }

    public function view(User $actor, EventRegistration $registration): bool
    {
        return $actor->can('events.registrations');
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, EventRegistration $registration): bool
    {
        return $actor->can('events.registrations');
    }

    public function delete(User $actor, EventRegistration $registration): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
