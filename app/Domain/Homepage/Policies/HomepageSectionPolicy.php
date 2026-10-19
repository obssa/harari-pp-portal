<?php

namespace App\Domain\Homepage\Policies;

use App\Domain\Homepage\Models\HomepageSection;
use App\Models\User;

/**
 * The homepage builder (PLAN.md §8): publishers and admins arrange it.
 */
final class HomepageSectionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('homepage.manage');
    }

    public function view(User $actor, HomepageSection $section): bool
    {
        return $actor->can('homepage.manage');
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, HomepageSection $section): bool
    {
        return $actor->can('homepage.manage');
    }

    public function delete(User $actor, HomepageSection $section): bool
    {
        return false;
    }
}
