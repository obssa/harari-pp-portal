<?php

namespace App\Domain\Access\Enums;

/**
 * Narrows a role to a slice of the portal (PLAN.md §8): one office, one
 * league, or one language.
 */
enum ScopeType: string
{
    case Office = 'office';
    case League = 'league';
    case Language = 'language';

    public function label(): string
    {
        return __('access.scope.'.$this->value);
    }
}
