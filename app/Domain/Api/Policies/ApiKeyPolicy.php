<?php

namespace App\Domain\Api\Policies;

use App\Domain\Api\Models\ApiKey;
use App\Models\User;

/**
 * API keys let outside apps read the portal: Super Admins only
 * (integrations.manage). Keys are revoked, never deleted, so the audit log
 * keeps which key read what and when.
 */
final class ApiKeyPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('integrations.manage');
    }

    public function view(User $actor, ApiKey $key): bool
    {
        return $actor->can('integrations.manage');
    }

    public function create(User $actor): bool
    {
        return $actor->can('integrations.manage');
    }

    public function update(User $actor, ApiKey $key): bool
    {
        return $actor->can('integrations.manage') && $key->revoked_at === null;
    }

    public function revoke(User $actor, ApiKey $key): bool
    {
        return $actor->can('integrations.manage') && $key->revoked_at === null;
    }

    public function delete(User $actor, ApiKey $key): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function restore(User $actor, ApiKey $key): bool
    {
        return false;
    }

    public function restoreAny(User $actor): bool
    {
        return false;
    }

    public function forceDelete(User $actor, ApiKey $key): bool
    {
        return false;
    }

    public function forceDeleteAny(User $actor): bool
    {
        return false;
    }

    public function replicate(User $actor, ApiKey $key): bool
    {
        return false;
    }

    public function reorder(User $actor): bool
    {
        return false;
    }
}
