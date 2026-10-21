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

        return trim($first.' '.implode(' ', array_map(fn (string $part): string => mb_substr($part, 0, 1).'.', $parts)));
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
