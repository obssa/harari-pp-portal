<?php

namespace App\Domain\Events\Enums;

/**
 * Where a registration stands (ADR-0025). Checking in is recorded with its
 * time, so a checked-in person stays "registered".
 */
enum RegistrationStatus: string
{
    case Registered = 'registered';
    case Waitlisted = 'waitlisted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __("events.registration.status.{$this->value}");
    }

    public function color(): string
    {
        return match ($this) {
            self::Registered => 'success',
            self::Waitlisted => 'warning',
            self::Cancelled => 'gray',
        };
    }
}
