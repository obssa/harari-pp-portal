<?php

namespace App\Domain\Dashboard\Policies;

use App\Domain\Dashboard\Models\Announcement;
use App\Models\User;

/**
 * Announcements to staff (PLAN.md §7): written by Super Admins only.
 */
final class AnnouncementPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function view(User $actor, Announcement $announcement): bool
    {
        return $actor->isSuperAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function update(User $actor, Announcement $announcement): bool
    {
        return $actor->isSuperAdmin();
    }

    public function delete(User $actor, Announcement $announcement): bool
    {
        return $actor->isSuperAdmin();
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
