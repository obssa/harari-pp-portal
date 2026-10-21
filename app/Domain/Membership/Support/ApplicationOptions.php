<?php

namespace App\Domain\Membership\Support;

/**
 * The fixed choices of the membership form (ADR-0044), with their labels in
 * the visitor's language.
 */
final class ApplicationOptions
{
    public const SEXES = ['female', 'male'];

    public const ID_TYPES = ['fayda', 'kebele', 'passport', 'none'];

    public const EDUCATION = ['none', 'primary', 'secondary', 'certificate', 'diploma', 'degree', 'masters', 'doctorate'];

    /** Youngest age to apply. */
    public const MINIMUM_AGE = 18;

    /**
     * @return array<string, string>
     */
    public static function sexes(): array
    {
        return [
            'female' => __('membership.sex.female'),
            'male' => __('membership.sex.male'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function idTypes(): array
    {
        return [
            'fayda' => __('membership.id_type.fayda'),
            'kebele' => __('membership.id_type.kebele'),
            'passport' => __('membership.id_type.passport'),
            'none' => __('membership.id_type.none'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function education(): array
    {
        return [
            'none' => __('membership.education.none'),
            'primary' => __('membership.education.primary'),
            'secondary' => __('membership.education.secondary'),
            'certificate' => __('membership.education.certificate'),
            'diploma' => __('membership.education.diploma'),
            'degree' => __('membership.education.degree'),
            'masters' => __('membership.education.masters'),
            'doctorate' => __('membership.education.doctorate'),
        ];
    }
}
