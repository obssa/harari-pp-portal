<?php

namespace App\Domain\Donations\Policies;

use App\Domain\Donations\Models\Donation;
use App\Models\User;

/**
 * Donations (ADR-0060): seen with donations.view, recorded and cancelled
 * with donations.manage; Admins and Super Admins hold both by default.
 * Pledges come only from the donate page and are never deleted.
 */
final class DonationPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('donations.view');
    }

    public function view(User $actor, Donation $donation): bool
    {
        return $actor->can('donations.view');
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, Donation $donation): bool
    {
        return $actor->can('donations.manage') && $donation->erased_at === null;
    }

    public function delete(User $actor, Donation $donation): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function restore(User $actor, Donation $donation): bool
    {
        return false;
    }

    public function restoreAny(User $actor): bool
    {
        return false;
    }

    public function forceDelete(User $actor, Donation $donation): bool
    {
        return false;
    }

    public function forceDeleteAny(User $actor): bool
    {
        return false;
    }

    public function replicate(User $actor, Donation $donation): bool
    {
        return false;
    }

    public function reorder(User $actor): bool
    {
        return false;
    }
}
