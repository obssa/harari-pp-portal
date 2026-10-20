<?php

namespace App\Domain\Documents\Policies;

use App\Domain\Documents\Models\DocumentVersion;
use App\Models\User;

/**
 * Earlier versions are seen by whoever sees documents; they are made by
 * replacing a file and never edited or deleted by hand (ADR-0026).
 */
final class DocumentVersionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('documents.view') || $actor->can('documents.manage');
    }

    public function view(User $actor, DocumentVersion $version): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, DocumentVersion $version): bool
    {
        return false;
    }

    public function delete(User $actor, DocumentVersion $version): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
