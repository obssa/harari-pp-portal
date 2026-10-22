<?php

namespace App\Support;

/**
 * Two letters for a person without a photo, skipping honorifics such as
 * "H.E", "Ato" or "Obbo" so "ክቡር አቶ ጌቱ ወዬሳ ቶላ" gives "ጌወ" (ADR-0066).
 */
final class Initials
{
    private const HONORIFICS = ['ክቡር', 'ክብርት', 'አቶ', 'ወይዘሮ', 'ወ/ሮ', 'ወ/ሪት', 'ወይዘሪት', 'ዶ/ር', 'ኢንጂነር', 'ወጣት', 'kabajamoo', 'kabajamtuu', 'obbo', 'aadde', 'h.e', 'h.e.', 'durba', 'dr', 'dr.', 'mr', 'mr.', 'mrs', 'mrs.', 'ms', 'ms.'];

    public static function of(string $name): string
    {
        $words = array_values(array_filter(
            preg_split('/\s+/u', trim($name)) ?: [],
            fn (string $word): bool => $word !== '' && ! in_array(mb_strtolower($word), self::HONORIFICS, true),
        ));

        return mb_strtoupper(implode('', array_map(fn (string $word): string => mb_substr($word, 0, 1), array_slice($words, 0, 2))));
    }
}
