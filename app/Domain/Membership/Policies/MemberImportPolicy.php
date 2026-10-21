<?php

namespace App\Domain\Membership\Policies;

use App\Domain\Membership\Models\MemberImport;
use App\Models\User;

/**
 * Roll imports are for those who may import members (ADR-0047); they are
 * started, applied and rolled back through the import actions only.
 */
final class MemberImportPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('members.import');
    }

    public function view(User $actor, MemberImport $import): bool
    {
        return $actor->can('members.import');
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, MemberImport $import): bool
    {
        return false;
    }

    public function delete(User $actor, MemberImport $import): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
