<?php

namespace App\Domain\Content\Policies;

use App\Domain\Content\Models\Post;
use App\Models\User;

/**
 * Posts (PLAN.md §8): authors edit their own drafts, editors any post,
 * translators the text of any post; only publishers publish, and only
 * holders of `posts.delete` delete.
 */
final class PostPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('posts.view');
    }

    public function view(User $actor, Post $post): bool
    {
        return $actor->can('posts.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('posts.create');
    }

    public function update(User $actor, Post $post): bool
    {
        return $actor->can('posts.update')
            || $actor->can('posts.translate')
            || ($actor->can('posts.update_own') && $post->created_by === $actor->id);
    }

    /** Type, featured image, categories, tags, flags: everything but the text. */
    public function updateStructure(User $actor, Post $post): bool
    {
        return $actor->can('posts.update')
            || ($actor->can('posts.update_own') && $post->created_by === $actor->id);
    }

    public function publish(User $actor, Post $post): bool
    {
        return $actor->can('posts.publish');
    }

    public function delete(User $actor, Post $post): bool
    {
        return $actor->can('posts.delete');
    }

    public function deleteAny(User $actor): bool
    {
        return $actor->can('posts.delete');
    }

    public function restore(User $actor, Post $post): bool
    {
        return $actor->can('posts.delete');
    }

    public function restoreAny(User $actor): bool
    {
        return $actor->can('posts.delete');
    }

    public function forceDelete(User $actor, Post $post): bool
    {
        return false;
    }

    public function forceDeleteAny(User $actor): bool
    {
        return false;
    }

    public function replicate(User $actor, Post $post): bool
    {
        return $actor->can('posts.create');
    }

    public function reorder(User $actor): bool
    {
        return false;
    }
}
