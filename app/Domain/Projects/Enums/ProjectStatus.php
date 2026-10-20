<?php

namespace App\Domain\Projects\Enums;

/**
 * Where a project stands (PLAN.md §4: planned, ongoing, completed).
 */
enum ProjectStatus: string
{
    case Planned = 'planned';
    case Ongoing = 'ongoing';
    case Completed = 'completed';
    case Paused = 'paused';

    public function label(): string
    {
        return __("projects.status.{$this->value}");
    }

    /** The badge tone on public pages. */
    public function tone(): string
    {
        return match ($this) {
            self::Planned => 'neutral',
            self::Ongoing => 'primary',
            self::Completed => 'success',
            self::Paused => 'warning',
        };
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
