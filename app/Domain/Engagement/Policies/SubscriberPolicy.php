<?php

namespace App\Domain\Engagement\Policies;

use App\Domain\Engagement\Models\Subscriber;
use App\Models\User;

/**
 * Subscribers join and change their choices themselves (ADR-0051); staff
 * with `subscribers.view` see them, with `subscribers.manage` may
 * unsubscribe someone who asked by other means. Removal is a privacy request.
 */
final class SubscriberPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('subscribers.view');
    }

    public function view(User $actor, Subscriber $subscriber): bool
    {
        return $actor->can('subscribers.view');
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, Subscriber $subscriber): bool
    {
        return false;
    }

    public function delete(User $actor, Subscriber $subscriber): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
