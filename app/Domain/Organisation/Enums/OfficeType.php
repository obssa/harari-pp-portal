<?php

namespace App\Domain\Organisation\Enums;

/**
 * Kinds of office in the party structure (PLAN.md §3 Offices & Structures).
 */
enum OfficeType: string
{
    case Branch = 'branch';
    case Woreda = 'woreda';
    case Kebele = 'kebele';
    case Sector = 'sector';
    case BasicOrganisation = 'basic_org';

    public function label(?string $locale = null): string
    {
        return __("organisation.office_type.{$this->value}", [], $locale);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
