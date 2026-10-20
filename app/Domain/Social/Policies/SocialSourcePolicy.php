<?php

namespace App\Domain\Social\Policies;

use App\Domain\Social\Models\SocialSource;
use App\Models\User;

/**
 * Social sources (PLAN.md §8: Media Manager): `social.view` to see them,
 * `social.manage` to add, change, read now and remove them.
 */
final class SocialSourcePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('social.view') || $actor->can('social.manage');
    }

    public function view(User $actor, SocialSource $source): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $actor->can('social.manage');
    }

    public function update(User $actor, SocialSource $source): bool
    {
        return $actor->can('social.manage');
    }

    public function delete(User $actor, SocialSource $source): bool
    {
        return $actor->can('social.manage');
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
