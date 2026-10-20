<?php

namespace App\Domain\Forms\Enums;

/**
 * Where a ticket stands (PLAN.md §7 inbox: new, assigned, in progress,
 * resolved). The public status page shows only this.
 */
enum SubmissionStatus: string
{
    case New = 'new';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';

    public function label(): string
    {
        return __("forms.status.{$this->value}");
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::Assigned => 'info',
            self::InProgress => 'primary',
            self::Resolved => 'success',
        };
    }

    public function isOpen(): bool
    {
        return $this !== self::Resolved;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }
}
