<?php

namespace App\Domain\Import\Policies;

use App\Domain\Import\Models\ContentSource;
use App\Models\User;

/**
 * Content sources (ADR-0022): admins see them and run an import; Super
 * Admins (who change settings) also switch them on or off; new sources are
 * added by developers after the branch confirms the channel.
 */
final class ContentSourcePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('settings.view');
    }

    public function view(User $actor, ContentSource $source): bool
    {
        return $actor->can('settings.view');
    }

    public function import(User $actor, ContentSource $source): bool
    {
        return $actor->can('settings.view') && $source->enabled;
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, ContentSource $source): bool
    {
        return $actor->can('settings.manage');
    }

    public function delete(User $actor, ContentSource $source): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
