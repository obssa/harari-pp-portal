<?php

namespace App\Domain\Forms\Support;

use App\Domain\Access\Support\OfficeScope;
use App\Domain\Forms\Models\FormSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Which tickets a person may handle (PLAN.md §8: support agents and office
 * heads per office, ADR-0028). A role held without a scope covers every
 * office; a role scoped to offices covers those offices only. Tickets of no
 * office belong to staff without a scope.
 */
final class InboxScope
{
    /**
     * @return list<string>|null null for every office
     */
    public static function officeIds(User $user, string $permission = 'inbox.view'): ?array
    {
        return OfficeScope::officeIds($user, $permission);
    }

    /**
     * @param  Builder<FormSubmission>  $query
     * @return Builder<FormSubmission>
     */
    public static function apply(Builder $query, User $user, string $permission = 'inbox.view'): Builder
    {
        $offices = self::officeIds($user, $permission);

        return $offices === null ? $query : $query->whereIn('office_id', $offices);
    }

    public static function covers(User $user, FormSubmission $submission, string $permission = 'inbox.view'): bool
    {
        return OfficeScope::covers($user, $submission->office_id, $permission);
    }
}
