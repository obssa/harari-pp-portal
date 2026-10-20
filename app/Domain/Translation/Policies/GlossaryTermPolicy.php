<?php

namespace App\Domain\Translation\Policies;

use App\Domain\Translation\Models\GlossaryTerm;
use App\Models\User;

/**
 * The glossary (ADR-0034): read with `translations.view`, kept by those
 * with `translations.glossary`.
 */
final class GlossaryTermPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('translations.view') || $actor->can('translations.glossary');
    }

    public function view(User $actor, GlossaryTerm $term): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $actor->can('translations.glossary');
    }

    public function update(User $actor, GlossaryTerm $term): bool
    {
        return $actor->can('translations.glossary');
    }

    public function delete(User $actor, GlossaryTerm $term): bool
    {
        return $actor->can('translations.glossary');
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
