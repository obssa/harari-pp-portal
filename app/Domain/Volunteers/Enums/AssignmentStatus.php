<?php

namespace App\Domain\Volunteers\Enums;

/**
 * An assignment is planned, then done (with the hours given) or cancelled.
 */
enum AssignmentStatus: string
{
    case Planned = 'planned';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __("volunteers.assignment.status.{$this->value}");
    }

    public function color(): string
    {
        return match ($this) {
            self::Planned => 'info',
            self::Done => 'success',
            self::Cancelled => 'gray',
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
