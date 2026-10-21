<?php

namespace App\Domain\Membership\Enums;

/**
 * Where a membership application stands (PLAN.md §7 funnel: started → phone
 * verified and submitted → approved or rejected; ADR-0044).
 */
enum ApplicationStatus: string
{
    case Started = 'started';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Started => __('membership.status.started'),
            self::Submitted => __('membership.status.submitted'),
            self::Approved => __('membership.status.approved'),
            self::Rejected => __('membership.status.rejected'),
            self::Withdrawn => __('membership.status.withdrawn'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Submitted => 'warning',
            default => 'gray',
        };
    }
}
