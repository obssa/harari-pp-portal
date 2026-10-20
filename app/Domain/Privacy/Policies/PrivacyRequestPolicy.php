<?php

namespace App\Domain\Privacy\Policies;

use App\Domain\Privacy\Models\PrivacyRequest;
use App\Models\User;

/**
 * The privacy request log is read by those who handle requests; nobody edits
 * or deletes it (ADR-0041).
 */
final class PrivacyRequestPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('privacy.manage');
    }

    public function view(User $actor, PrivacyRequest $request): bool
    {
        return $actor->can('privacy.manage');
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, PrivacyRequest $request): bool
    {
        return false;
    }

    public function delete(User $actor, PrivacyRequest $request): bool
    {
        return false;
    }
}
