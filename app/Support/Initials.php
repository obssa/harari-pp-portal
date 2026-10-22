<?php

namespace App\Support;

/**
 * Two letters for a person without a photo, skipping honorifics such as
 * "Ato" or "Obbo" so "አቶ ጌቱ ወዬሳ" gives "ጌወ" (ADR-0066).
 */
final class Initials
{
    private const HONORIFICS = ['አቶ', 'ወይዘሮ', 'ወ/ሮ', 'ወ/ሪት', 'ወይዘሪት', 'ዶ/ር', 'ኢንጂነር', 'ወጣት', 'obbo', 'aadde', 'durba', 'dr', 'dr.', 'mr', 'mr.', 'mrs', 'mrs.', 'ms', 'ms.'];

    public static function of(string $name): string
    {
        $words = array_values(array_filter(
            preg_split('/\s+/u', trim($name)) ?: [],
            fn (string $word): bool => $word !== '' && ! in_array(mb_strtolower($word), self::HONORIFICS, true),
        ));

        return mb_strtoupper(implode('', array_map(fn (string $word): string => mb_substr($word, 0, 1), array_slice($words, 0, 2))));
    }
}
