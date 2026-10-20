<?php

namespace App\Domain\Analytics\Policies;

use App\Domain\Analytics\Models\AnalyticsReport;
use App\Models\User;

/**
 * Scheduled reports send traffic figures out by email, so they need the
 * export permission (ADR-0040).
 */
final class AnalyticsReportPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('analytics.export');
    }

    public function view(User $actor, AnalyticsReport $report): bool
    {
        return $actor->can('analytics.export');
    }

    public function create(User $actor): bool
    {
        return $actor->can('analytics.export');
    }

    public function update(User $actor, AnalyticsReport $report): bool
    {
        return $actor->can('analytics.export');
    }

    public function delete(User $actor, AnalyticsReport $report): bool
    {
        return $actor->can('analytics.export');
    }

    public function deleteAny(User $actor): bool
    {
        return $actor->can('analytics.export');
    }
}
