<?php

namespace App\Domain\Homepage\Policies;

use App\Domain\Homepage\Models\HeroSlide;
use App\Models\User;

/**
 * The homepage carousel (ADR-0070) belongs to the homepage builder: those
 * who arrange the homepage manage its slides.
 */
final class HeroSlidePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('homepage.manage');
    }

    public function view(User $actor, HeroSlide $slide): bool
    {
        return $actor->can('homepage.manage');
    }

    public function create(User $actor): bool
    {
        return $actor->can('homepage.manage');
    }

    public function update(User $actor, HeroSlide $slide): bool
    {
        return $actor->can('homepage.manage');
    }

    public function delete(User $actor, HeroSlide $slide): bool
    {
        return $actor->can('homepage.manage');
    }

    public function deleteAny(User $actor): bool
    {
        return $actor->can('homepage.manage');
    }

    public function reorder(User $actor): bool
    {
        return $actor->can('homepage.manage');
    }
}
