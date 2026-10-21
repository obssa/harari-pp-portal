<?php

namespace App\Domain\Membership\Policies;

use App\Domain\Access\Support\OfficeScope;
use App\Domain\Membership\Models\Member;
use App\Models\User;

/**
 * Members are seen and kept up to date by the officers of their woreda
 * (PLAN.md §8, ADR-0045); removing a member is a privacy request, not a
 * delete button.
 */
final class MemberPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('members.view');
    }

    public function view(User $actor, Member $member): bool
    {
        return OfficeScope::covers($actor, $member->woreda_id, 'members.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('members.create');
    }

    public function update(User $actor, Member $member): bool
    {
        return OfficeScope::covers($actor, $member->woreda_id, 'members.update');
    }

    public function delete(User $actor, Member $member): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
