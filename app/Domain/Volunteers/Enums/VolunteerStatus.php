<?php

namespace App\Domain\Volunteers\Enums;

/**
 * Where a volunteer stands (ADR-0048): new until a coordinator has been in
 * touch, then active or inactive.
 */
enum VolunteerStatus: string
{
    case New = 'new';
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return __("volunteers.status.{$this->value}");
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::Active => 'success',
            self::Inactive => 'gray',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_combine(array_map(fn (self $s): string => $s->value, self::cases()), array_map(fn (self $s): string => $s->label(), self::cases()));
    }
}
