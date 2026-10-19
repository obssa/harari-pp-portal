<?php

namespace App\Domain\Search\Support;

use App\Domain\Content\Support\Slugger;
use Illuminate\Support\Str;

/**
 * The text that search matches against (ADR-0020). Both the stored text and
 * the visitor's words go through `normalize()`, so spelling variants match:
 *
 * - Ethiopic letters that sound alike and are written either way are made
 *   one (ሐ ኀ → ሀ, ሠ → ሰ, ዐ → አ, ፀ → ጸ, and the fourth forms ሃ ኣ → ሀ አ);
 * - Ethiopic punctuation becomes a space, Latin letters lose case and
 *   accents.
 *
 * Text with Ethiopic letters also stores its Latin spelling, so a visitor
 * typing "medemer" finds መደመር.
 */
final class SearchText
{
    /** Longest text kept per record; enough for long speeches. */
    public const MAX_LENGTH = 60000;

    /** @var array<string, string>|null */
    private static ?array $map = null;

    /**
     * @param  list<?string>  $parts
     */
    public static function build(array $parts): string
    {
        $text = self::normalize(implode(' ', array_filter($parts, fn (?string $part): bool => filled($part))));
        if (preg_match('/\p{Ethiopic}/u', $text) === 1) {
            $latin = trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower(app(Slugger::class)->transliterate($text))) ?? '');
            $text .= ' '.$latin;
        }

        return mb_substr($text, 0, self::MAX_LENGTH);
    }

    public static function normalize(string $text): string
    {
        $text = strtr($text, self::map());
        $text = Str::lower(self::latinFold($text));
        $text = preg_replace('/[\x{1360}-\x{1368}\s\p{P}\p{S}]+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * The visitor's words, normalized, at most six, each at least two letters.
     *
     * @return list<string>
     */
    public static function words(string $query): array
    {
        $words = array_values(array_filter(
            explode(' ', self::normalize($query)),
            fn (string $word): bool => mb_strlen($word) >= 2,
        ));

        return array_slice(array_values(array_unique($words)), 0, 6);
    }

    /** Removes accents from Latin letters only; Ethiopic is left as it is. */
    private static function latinFold(string $text): string
    {
        return (string) preg_replace_callback('/[\p{Latin}]+/u', fn (array $m): string => Str::ascii($m[0]), $text);
    }

    /**
     * @return array<string, string>
     */
    private static function map(): array
    {
        if (self::$map !== null) {
            return self::$map;
        }

        $map = [];
        // Whole syllable series: 7 orders each.
        $series = [
            ['ሐ', 'ሀ'], ['ኀ', 'ሀ'], ['ሠ', 'ሰ'], ['ዐ', 'አ'], ['ፀ', 'ጸ'],
        ];
        foreach ($series as [$from, $to]) {
            $start = mb_ord($from);
            $target = mb_ord($to);
            for ($order = 0; $order < 7; $order++) {
                $map[mb_chr($start + $order)] = mb_chr($target + $order);
            }
        }
        // Fourth forms that read like the first, also when reached through a series above.
        $fourth = ['ሃ' => 'ሀ', 'ኣ' => 'አ'];
        $map = array_map(fn (string $to): string => $fourth[$to] ?? $to, $map) + $fourth;

        return self::$map = $map;
    }
}
