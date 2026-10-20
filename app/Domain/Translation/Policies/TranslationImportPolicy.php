<?php

namespace App\Domain\Translation\Policies;

use App\Domain\Translation\Models\TranslationImport;
use App\Models\User;

/**
 * Translation imports (ADR-0033): seen with `translations.view`, rolled back
 * or discarded by those who may import that language; never edited.
 */
final class TranslationImportPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('translations.view');
    }

    public function view(User $actor, TranslationImport $import): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, TranslationImport $import): bool
    {
        return $actor->can('translations.import') && $actor->canEditTranslationsFor($import->locale);
    }

    public function delete(User $actor, TranslationImport $import): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
