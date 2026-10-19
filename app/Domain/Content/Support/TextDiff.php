<?php

namespace App\Domain\Content\Support;

/**
 * A paragraph-by-paragraph comparison of two texts (longest common
 * subsequence), for the revision history.
 */
final class TextDiff
{
    /** Above this many paragraphs per side, unchanged runs are not searched for. */
    private const MAX_LINES = 600;

    /**
     * @return list<array{type: 'same'|'added'|'removed', text: string}>
     */
    public static function lines(string $before, string $after): array
    {
        $old = self::split($before);
        $new = self::split($after);

        if (count($old) > self::MAX_LINES || count($new) > self::MAX_LINES) {
            return [
                ...array_map(fn (string $text): array => ['type' => 'removed', 'text' => $text], $old),
                ...array_map(fn (string $text): array => ['type' => 'added', 'text' => $text], $new),
            ];
        }

        $rows = count($old);
        $cols = count($new);
        $lcs = array_fill(0, $rows + 1, array_fill(0, $cols + 1, 0));
        for ($i = $rows - 1; $i >= 0; $i--) {
            for ($j = $cols - 1; $j >= 0; $j--) {
                $lcs[$i][$j] = $old[$i] === $new[$j]
                    ? $lcs[$i + 1][$j + 1] + 1
                    : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }

        $result = [];
        $i = 0;
        $j = 0;
        while ($i < $rows && $j < $cols) {
            if ($old[$i] === $new[$j]) {
                $result[] = ['type' => 'same', 'text' => $old[$i]];
                $i++;
                $j++;
            } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
                $result[] = ['type' => 'removed', 'text' => $old[$i]];
                $i++;
            } else {
                $result[] = ['type' => 'added', 'text' => $new[$j]];
                $j++;
            }
        }
        for (; $i < $rows; $i++) {
            $result[] = ['type' => 'removed', 'text' => $old[$i]];
        }
        for (; $j < $cols; $j++) {
            $result[] = ['type' => 'added', 'text' => $new[$j]];
        }

        return $result;
    }

    public static function changed(string $before, string $after): bool
    {
        return self::split($before) !== self::split($after);
    }

    /**
     * @return list<string>
     */
    private static function split(string $text): array
    {
        $lines = preg_split('/\R+/u', trim($text)) ?: [];

        return array_values(array_filter(array_map('trim', $lines), fn (string $line): bool => $line !== ''));
    }
}
