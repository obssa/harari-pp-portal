<?php

namespace App\Domain\Content\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * URL slugs for every language (PLAN.md §6): Ethiopic titles are written in
 * Latin letters with a simple, readable transliteration (ብልጽግና → biltsigina),
 * then cleaned like any slug. Editors can always type their own slug.
 */
final class Slugger
{
    public const MAX_LENGTH = 120;

    /** First syllable of each Ethiopic consonant row => Latin consonant. */
    private const ROWS = [
        0x1200 => 'h', 0x1208 => 'l', 0x1210 => 'h', 0x1218 => 'm', 0x1220 => 's', 0x1228 => 'r',
        0x1230 => 's', 0x1238 => 'sh', 0x1240 => 'q', 0x1250 => 'qh', 0x1260 => 'b', 0x1268 => 'v',
        0x1270 => 't', 0x1278 => 'ch', 0x1280 => 'h', 0x1290 => 'n', 0x1298 => 'ny', 0x12A0 => '',
        0x12A8 => 'k', 0x12B8 => 'kh', 0x12C8 => 'w', 0x12D0 => '', 0x12D8 => 'z', 0x12E0 => 'zh',
        0x12E8 => 'y', 0x12F0 => 'd', 0x12F8 => 'dd', 0x1300 => 'j', 0x1308 => 'g', 0x1318 => 'ng',
        0x1320 => 't', 0x1328 => 'ch', 0x1330 => 'p', 0x1338 => 'ts', 0x1340 => 'ts', 0x1348 => 'f',
        0x1350 => 'p',
    ];

    /** Labialised rows (ቈ, ኰ, ጐ ...): five forms each. */
    private const LABIALISED = [
        0x1248 => 'qw', 0x1258 => 'qhw', 0x1288 => 'hw', 0x12B0 => 'kw', 0x12C0 => 'khw', 0x1310 => 'gw',
    ];

    /** Vowel of each order: ä, u, i, a, e, ə (6th), o, wa. */
    private const ORDERS = ['e', 'u', 'i', 'a', 'e', '', 'o', 'wa'];

    private const LABIALISED_ORDERS = ['e', null, 'i', 'a', 'e', ''];

    /** Rows whose first order is pronounced "a" (ሀ ሐ ኀ አ ዐ). */
    private const A_ROWS = [0x1200, 0x1210, 0x1280, 0x12A0, 0x12D0];

    /** Rows of the glottal stops, whose 6th order is "i" (እ). */
    private const GLOTTAL = [0x12A0, 0x12D0];

    private const NUMERALS = [
        0x1369 => 1, 0x136A => 2, 0x136B => 3, 0x136C => 4, 0x136D => 5, 0x136E => 6, 0x136F => 7,
        0x1370 => 8, 0x1371 => 9, 0x1372 => 10, 0x1373 => 20, 0x1374 => 30, 0x1375 => 40, 0x1376 => 50,
        0x1377 => 60, 0x1378 => 70, 0x1379 => 80, 0x137A => 90, 0x137B => 100, 0x137C => 10000,
    ];

    /**
     * A slug for this text, unique among `$table` rows of the same locale.
     */
    public function unique(string $text, string $table, string $locale, int|string|null $ignoreId = null, string $fallback = 'item'): string
    {
        $base = $this->slug($text);
        if ($base === '') {
            $base = $fallback;
        }

        $candidate = $base;
        for ($i = 2; $this->taken($table, $locale, $candidate, $ignoreId); $i++) {
            $suffix = '-'.$i;
            $candidate = Str::limit($base, self::MAX_LENGTH - strlen($suffix), '').$suffix;
        }

        return $candidate;
    }

    public function slug(string $text): string
    {
        return Str::limit(Str::slug($this->transliterate($text)), self::MAX_LENGTH, '');
    }

    /**
     * Ethiopic letters to Latin; other text is left as it is.
     */
    public function transliterate(string $text): string
    {
        $chars = mb_str_split($text);
        $out = '';
        $count = count($chars);

        for ($i = 0; $i < $count; $i++) {
            $code = mb_ord($chars[$i]);

            if (isset(self::NUMERALS[$code])) {
                [$value, $i] = $this->numeral($chars, $i);
                $out .= ' '.$value.' ';

                continue;
            }

            if ($code >= 0x1360 && $code <= 0x1368) {
                // ፡ ። ፣ ፤ ፥ ፦ ፧ ፨ separate words.
                $out .= ' ';

                continue;
            }

            $syllable = $this->syllable($code, $this->endsWord($chars, $i + 1));
            $out .= $syllable ?? $chars[$i];
        }

        return $out;
    }

    private function syllable(int $code, bool $wordEnd): ?string
    {
        if ($code < 0x1200 || $code > 0x1357) {
            return null;
        }

        foreach (self::LABIALISED as $start => $consonant) {
            if ($code >= $start && $code < $start + 6) {
                $vowel = self::LABIALISED_ORDERS[$code - $start];

                return $vowel === null ? null : $consonant.$vowel;
            }
        }

        $start = $code - (($code - 0x1200) % 8);
        if (! array_key_exists($start, self::ROWS)) {
            return null;
        }

        $order = $code - $start;
        $vowel = self::ORDERS[$order];

        if ($order === 0 && in_array($start, self::A_ROWS, true)) {
            $vowel = 'a';
        }

        if ($order === 5) {
            $vowel = in_array($start, self::GLOTTAL, true) || ! $wordEnd ? 'i' : '';
        }

        return self::ROWS[$start].$vowel;
    }

    /**
     * @param  list<string>  $chars
     */
    private function endsWord(array $chars, int $next): bool
    {
        if (! isset($chars[$next])) {
            return true;
        }

        $code = mb_ord($chars[$next]);

        return ! ($code >= 0x1200 && $code <= 0x1357);
    }

    /**
     * Reads a run of Ethiopic numerals (፲፫ = 13, ፻፳ = 120, ፳፻፲፱ = 2019).
     *
     * @param  list<string>  $chars
     * @return array{0: string, 1: int} digits, index of the last numeral read
     */
    private function numeral(array $chars, int $i): array
    {
        $total = 0;
        $group = 0;
        $count = count($chars);

        for (; $i < $count; $i++) {
            $value = self::NUMERALS[mb_ord($chars[$i])] ?? null;
            if ($value === null) {
                break;
            }

            if ($value === 100) {
                $group = ($group === 0 ? 1 : $group) * 100;
            } elseif ($value === 10000) {
                $total += ($group === 0 ? 1 : $group) * 10000;
                $group = 0;
            } else {
                $group += $value;
            }
        }

        return [(string) ($total + $group), $i - 1];
    }

    private function taken(string $table, string $locale, string $slug, int|string|null $ignoreId): bool
    {
        return DB::table($table)
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }
}
