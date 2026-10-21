<?php

namespace App\Domain\Membership\Support;

use App\Support\PhoneNumber;

/**
 * Personal details shortened for lists (PLAN.md §11: masked in lists,
 * ADR-0045): enough to recognise a person, not to copy their details.
 */
final class Mask
{
    /** "Sample Applicant Person" → "Sample A. P." */
    public static function name(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        if ($parts === [] || $parts[0] === '') {
            return '—';
        }
        $first = array_shift($parts);
        // Initials of the other names; brackets and other signs are not names.
        $initials = array_filter(array_map(fn (string $part): string => mb_strtoupper(mb_substr((string) preg_replace('/[^\p{L}\p{M}]/u', '', $part), 0, 1)), $parts));

        return trim($first.' '.implode(' ', array_map(fn (string $initial): string => $initial.'.', $initials)));
    }

    /** "+251911234567" → "+251••••••567" (the same mask as elsewhere in the portal) */
    public static function phone(?string $phone): string
    {
        return $phone === null || $phone === '' ? '—' : PhoneNumber::mask($phone);
    }

    /** "123456789012" → "••••••••9012" */
    public static function idNumber(string $number): string
    {
        return str_repeat('•', max(0, strlen($number) - 4)).substr($number, -4);
    }
}
