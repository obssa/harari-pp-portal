<?php

namespace App\Domain\Webhooks\Policies;

use App\Domain\Webhooks\Models\Webhook;
use App\Models\User;

/**
 * Webhooks send data to outside systems: Super Admins only (integrations.manage).
 */
final class WebhookPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('integrations.manage');
    }

    public function view(User $actor, Webhook $webhook): bool
    {
        return $actor->can('integrations.manage');
    }

    public function create(User $actor): bool
    {
        return $actor->can('integrations.manage');
    }

    public function update(User $actor, Webhook $webhook): bool
    {
        return $actor->can('integrations.manage');
    }

    public function delete(User $actor, Webhook $webhook): bool
    {
        return $actor->can('integrations.manage');
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function restore(User $actor, Webhook $webhook): bool
    {
        return false;
    }

    public function restoreAny(User $actor): bool
    {
        return false;
    }

    public function forceDelete(User $actor, Webhook $webhook): bool
    {
        return false;
    }

    public function forceDeleteAny(User $actor): bool
    {
        return false;
    }

    public function replicate(User $actor, Webhook $webhook): bool
    {
        return false;
    }

    public function reorder(User $actor): bool
    {
        return false;
    }
}
