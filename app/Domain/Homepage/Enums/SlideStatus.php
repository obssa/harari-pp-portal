<?php

namespace App\Domain\Homepage\Enums;

/**
 * Where a carousel slide stands now (ADR-0070).
 */
enum SlideStatus: string
{
    case Live = 'live';
    case Scheduled = 'scheduled';
    case Ended = 'ended';
    case Off = 'off';

    public function label(): string
    {
        return __("homepage.carousel.status.{$this->value}");
    }

    public function color(): string
    {
        return match ($this) {
            self::Live => 'success',
            self::Scheduled => 'info',
            self::Ended => 'gray',
            self::Off => 'warning',
        };
    }
}
