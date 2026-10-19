<?php

namespace App\Domain\Content\Policies;

use App\Domain\Content\Models\Comment;
use App\Models\User;

/**
 * Internal comments: read by staff who work on content; written through
 * the post screen (which checks that the person may edit the post); never
 * edited or deleted, so the record of a review stays intact.
 */
final class CommentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('posts.view') || $actor->can('pages.view');
    }

    public function view(User $actor, Comment $comment): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $actor->can('posts.view');
    }

    public function update(User $actor, Comment $comment): bool
    {
        return false;
    }

    public function delete(User $actor, Comment $comment): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function restore(User $actor, Comment $comment): bool
    {
        return false;
    }

    public function restoreAny(User $actor): bool
    {
        return false;
    }

    public function forceDelete(User $actor, Comment $comment): bool
    {
        return false;
    }

    public function forceDeleteAny(User $actor): bool
    {
        return false;
    }

    public function replicate(User $actor, Comment $comment): bool
    {
        return false;
    }

    public function reorder(User $actor): bool
    {
        return false;
    }

    public function associate(User $actor, Comment $comment): bool
    {
        return false;
    }

    public function dissociate(User $actor, Comment $comment): bool
    {
        return false;
    }
}
