<?php

namespace App\Domain\Engagement\Support;

/**
 * How many SMS parts a text takes (ADR-0052). Latin text in the GSM alphabet
 * fits 160 characters in one message and 153 per part after that; anything
 * else, Ethiopic included, is sent as Unicode: 70 in one message, 67 per part.
 */
final class SmsLength
{
    /** At most this many parts per campaign message, stop link included. */
    public const MAX_PARTS = 4;

    private const GSM = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà^{}\\[~]|€";

    /**
     * @return array{characters: int, parts: int, unicode: bool}
     */
    public static function measure(string $text): array
    {
        $characters = mb_strlen($text);
        $unicode = false;
        foreach (mb_str_split($text) as $character) {
            if (! str_contains(self::GSM, $character)) {
                $unicode = true;

                break;
            }
        }
        [$single, $part] = $unicode ? [70, 67] : [160, 153];

        return ['characters' => $characters, 'parts' => $characters <= $single ? 1 : (int) ceil($characters / $part), 'unicode' => $unicode];
    }
}
