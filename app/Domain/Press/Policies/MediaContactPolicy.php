<?php

namespace App\Domain\Press\Policies;

use App\Domain\Press\Models\MediaContact;
use App\Models\User;

/**
 * Media contacts are business contacts kept by press staff
 * (`media_contacts.manage`, ADR-0054).
 */
final class MediaContactPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('media_contacts.manage');
    }

    public function view(User $actor, MediaContact $contact): bool
    {
        return $actor->can('media_contacts.manage');
    }

    public function create(User $actor): bool
    {
        return $actor->can('media_contacts.manage');
    }

    public function update(User $actor, MediaContact $contact): bool
    {
        return $actor->can('media_contacts.manage');
    }

    public function delete(User $actor, MediaContact $contact): bool
    {
        return $actor->can('media_contacts.manage');
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
