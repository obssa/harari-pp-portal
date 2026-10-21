<?php

namespace App\Domain\Membership\Policies;

use App\Domain\Access\Support\OfficeScope;
use App\Domain\Membership\Models\MembershipApplication;
use App\Models\User;

/**
 * Applications hold sensitive personal details (ADR-0044, ADR-0045): seen by
 * Membership Officers for their woredas, decided by those who may review,
 * never created or deleted by hand (they come from the public form and are
 * removed by the retention rules or a privacy request).
 */
final class MembershipApplicationPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('applications.view');
    }

    public function view(User $actor, MembershipApplication $application): bool
    {
        return OfficeScope::covers($actor, $application->woreda_id, 'applications.view');
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, MembershipApplication $application): bool
    {
        return OfficeScope::covers($actor, $application->woreda_id, 'applications.review');
    }

    public function delete(User $actor, MembershipApplication $application): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
