<?php

namespace App\Domain\Privacy\Support;

/**
 * How long each kind of record is kept and what removes it (PLAN.md §11,
 * ADR-0041). The privacy page shows this list; each rule is enforced by the
 * scheduled command named in it.
 */
final class RetentionRules
{
    /**
     * @return list<array{data: string, kept: string, enforced: string}>
     */
    public static function all(): array
    {
        return [
            ['data' => __('privacy.retention.registrations'), 'kept' => __('privacy.retention.days_after_event', ['days' => (int) config('portal.registration_retention_days')]), 'enforced' => 'events:purge-registrations'],
            ['data' => __('privacy.retention.submissions'), 'kept' => __('privacy.retention.days_after_resolved', ['days' => (int) config('portal.submission_retention_days')]), 'enforced' => 'forms:purge-submissions'],
            ['data' => __('privacy.retention.members'), 'kept' => __('privacy.retention.members_rule'), 'enforced' => __('privacy.retention.on_request')],
            ['data' => __('privacy.retention.subscribers'), 'kept' => __('privacy.retention.subscribers_rule'), 'enforced' => 'membership:purge-applications'],
            ['data' => __('privacy.retention.volunteers'), 'kept' => __('privacy.retention.volunteers_rule'), 'enforced' => 'membership:purge-applications'],
            ['data' => __('privacy.retention.applications'), 'kept' => __('privacy.retention.applications_rule', ['days' => (int) config('portal.closed_application_retention_days')]), 'enforced' => 'membership:purge-applications'],
            ['data' => __('privacy.retention.audit'), 'kept' => __('privacy.retention.days', ['days' => (int) config('activitylog.delete_records_older_than_days')]), 'enforced' => 'activitylog:clean'],
            ['data' => __('privacy.retention.notifications'), 'kept' => __('privacy.retention.notifications_rule'), 'enforced' => 'notifications:prune'],
            ['data' => __('privacy.retention.failed_jobs'), 'kept' => __('privacy.retention.days', ['days' => 30]), 'enforced' => 'queue:prune-failed'],
            ['data' => __('privacy.retention.backups'), 'kept' => __('privacy.retention.backups_rule'), 'enforced' => 'backup:clean'],
            ['data' => __('privacy.retention.page_views'), 'kept' => __('privacy.retention.page_views_rule'), 'enforced' => 'model:prune'],
            ['data' => __('privacy.retention.search_terms'), 'kept' => __('privacy.retention.days', ['days' => 365]), 'enforced' => 'model:prune'],
            ['data' => __('privacy.retention.health'), 'kept' => __('privacy.retention.days', ['days' => 7]), 'enforced' => 'model:prune'],
            ['data' => __('privacy.retention.sessions'), 'kept' => __('privacy.retention.minutes', ['minutes' => (int) config('session.lifetime')]), 'enforced' => __('privacy.retention.automatic')],
            ['data' => __('privacy.retention.requests'), 'kept' => __('privacy.retention.forever'), 'enforced' => '—'],
        ];
    }
}
