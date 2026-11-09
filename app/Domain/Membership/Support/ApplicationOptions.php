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

    /** ADR-0076: the number on the Fayda card, the only document the public form accepts. */
    public const FAYDA_DIGITS = 16;

    /**
     * The Fayda number as typed, without the spaces and dashes people group it with.
     */
    public static function faydaDigits(string $typed): string
    {
        return (string) preg_replace('/[\s-]/', '', $typed);
    }

    /**
     * What is wrong with a Fayda number, in words the applicant can act on, or null.
     */
    public static function faydaError(string $typed): ?string
    {
        $digits = self::faydaDigits($typed);

        return match (true) {
            $digits === '' => __('membership.validation.fayda_required', ['digits' => self::FAYDA_DIGITS]),
            preg_match('/^\d+$/', $digits) !== 1 => __('membership.validation.fayda_digits'),
            strlen($digits) !== self::FAYDA_DIGITS => __('membership.validation.fayda_length', ['digits' => self::FAYDA_DIGITS, 'count' => strlen($digits)]),
            default => null,
        };
    }

    /**
     * What is wrong with a name typed on the public form, or null: letters of any
     * script with single spaces, and the apostrophe, hyphen or dot names use.
     */
    public static function nameError(string $name): ?string
    {
        return match (true) {
            $name === '' => __('membership.validation.name_required'),
            mb_strlen($name) > 120 => __('membership.validation.name_long', ['max' => 120]),
            mb_strlen($name) < 2 || preg_match("/^[\\p{L}\\p{M}]+(?:[ '’.\\-][\\p{L}\\p{M}]+)*\\.?$/u", $name) !== 1 => __('membership.validation.name_letters'),
            default => null,
        };
    }

    /** ADR-0080: the shortest password a member may choose. */
    public const PASSWORD_MIN = 8;

    /**
     * What is wrong with a new member password, or null: at least eight
     * characters with letters and digits, typed the same twice.
     */
    public static function passwordError(string $password, string $confirmation): ?string
    {
        return match (true) {
            $password === '' => __('membership.validation.password_required'),
            mb_strlen($password) < self::PASSWORD_MIN => __('membership.validation.password_short', ['min' => self::PASSWORD_MIN]),
            mb_strlen($password) > 120 => __('membership.validation.password_long', ['max' => 120]),
            preg_match('/\p{L}/u', $password) !== 1 || preg_match('/\d/', $password) !== 1 => __('membership.validation.password_weak'),
            $password !== $confirmation => __('membership.validation.password_mismatch'),
            default => null,
        };
    }

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
