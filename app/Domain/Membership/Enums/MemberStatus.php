<?php

namespace App\Domain\Membership\Enums;

/**
 * A member's standing (ADR-0045).
 */
enum MemberStatus: string
{
    case Active = 'active';
    case Lapsed = 'lapsed';
    case Suspended = 'suspended';
    case Resigned = 'resigned';
    case Deceased = 'deceased';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('members.status.active'),
            self::Lapsed => __('members.status.lapsed'),
            self::Suspended => __('members.status.suspended'),
            self::Resigned => __('members.status.resigned'),
            self::Deceased => __('members.status.deceased'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Lapsed => 'warning',
            self::Suspended => 'danger',
            default => 'gray',
        };
    }
}
