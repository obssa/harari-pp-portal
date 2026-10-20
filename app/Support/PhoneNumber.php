<?php

namespace App\Support;

/**
 * Phone numbers as typed by visitors, written the one international way
 * (E.164), so the same number is always recognised. Ethiopian numbers may
 * be typed as 09…, 07…, 9…, 2519… or +2519….
 */
final class PhoneNumber
{
    public static function normalise(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $plus = str_starts_with($value, '+') || str_starts_with($value, '00');
        $digits = (string) preg_replace('/\D+/', '', $value);
        if (str_starts_with($value, '00')) {
            $digits = substr($digits, 2);
        }

        // Ethiopian mobile numbers (Ethio Telecom 09…, Safaricom 07…).
        if (! $plus && preg_match('/^0?([79]\d{8})$/', $digits, $local) === 1) {
            return '+251'.$local[1];
        }
        if (preg_match('/^251([79]\d{8})$/', $digits, $ethiopian) === 1) {
            return '+251'.$ethiopian[1];
        }

        return $plus && preg_match('/^[1-9]\d{7,14}$/', $digits) === 1 ? '+'.$digits : null;
    }

    /** For logs and screens that must not show the whole number. */
    public static function mask(string $number): string
    {
        $length = strlen($number);

        return $length <= 6 ? str_repeat('•', $length) : substr($number, 0, 4).str_repeat('•', $length - 7).substr($number, -3);
    }
}
