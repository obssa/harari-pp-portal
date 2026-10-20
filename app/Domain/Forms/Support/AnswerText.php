<?php

namespace App\Domain\Forms\Support;

/**
 * An answer as staff read it in the inbox and the export.
 */
final class AnswerText
{
    public static function for(mixed $value): string
    {
        return match (true) {
            $value === true => __('forms.yes'),
            is_array($value) && array_key_exists('text', $value) => (string) $value['text'],
            is_array($value) => implode(', ', array_map(fn (mixed $item): string => self::for($item), $value)),
            is_scalar($value) => (string) $value,
            default => '',
        };
    }
}
