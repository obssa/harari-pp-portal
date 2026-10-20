<?php

namespace App\Domain\Social\Policies;

use App\Domain\Social\Models\SocialPost;
use App\Models\User;

/**
 * Posts come from the networks: staff with `social.manage` hide or pin
 * them, nobody writes or deletes them here.
 */
final class SocialPostPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('social.view') || $actor->can('social.manage');
    }

    public function view(User $actor, SocialPost $post): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, SocialPost $post): bool
    {
        return $actor->can('social.manage');
    }

    public function delete(User $actor, SocialPost $post): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
