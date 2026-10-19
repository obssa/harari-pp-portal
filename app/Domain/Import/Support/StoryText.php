<?php

namespace App\Domain\Import\Support;

use App\Domain\Search\Support\SearchText;
use Illuminate\Support\Str;

/**
 * Turns a channel message into a story: language, title, summary and
 * paragraphs, plus a fingerprint for spotting the same story elsewhere.
 * Text is kept as written (docs/content/sources.md rule 3).
 */
final class StoryText
{
    /** Common Afaan Oromo words, to tell Oromo from English in Latin script. */
    private const OROMO_WORDS = ['fi', 'kan', 'irratti', 'keessatti', 'naannoo', 'waliin', 'akka', 'hojii', 'ummata', 'bara', 'kana', 'jedhame', 'geggeeffame', 'irraa'];

    /** Common Harari words that Amharic does not use. */
    private const HARARI_WORDS = ['ኪም', 'ሐላ', 'ዛዩ', 'ሑሉፍ', 'ሩቡእ', 'አታጮት', 'ሞሸቤ', 'ላቂ', 'ታኹው', 'አርራ', 'ሑስኒ'];

    /** Above this share of shared words, two stories are the same story. */
    public const SAME_STORY = 0.6;

    public static function language(string $text, string $default): string
    {
        if (preg_match('/\p{Ethiopic}/u', $text) === 1) {
            // Amharic and Harari share the script: Harari is told by its own words and
            // the ዚ-/ዪ- forms Amharic hardly uses; otherwise the channel's language decides.
            return self::looksHarari($text) ? 'har' : $default;
        }

        $words = array_count_values(explode(' ', SearchText::normalize($text)));
        $oromo = array_sum(array_intersect_key($words, array_flip(self::OROMO_WORDS)));

        return $oromo >= 2 ? 'om' : 'en';
    }

    /** Three or more Harari signs: known Harari words or words starting with ዚ or ዪ. */
    public static function looksHarari(string $text): bool
    {
        $markers = array_map(fn (string $word): string => SearchText::normalize($word), self::HARARI_WORDS);
        $score = 0;
        foreach (explode(' ', SearchText::normalize($text)) as $word) {
            if (in_array($word, $markers, true)
                || (mb_strlen($word) >= 3 && in_array(mb_substr($word, 0, 1), ['ዚ', 'ዪ'], true))) {
                $score++;
            }
        }

        return $score >= 3;
    }

    /**
     * The first line is the headline; the rest, split at blank lines, the story.
     *
     * @return array{title: string, summary: ?string, paragraphs: list<string>}
     */
    public static function split(string $text): array
    {
        $text = trim($text);
        $break = strpos($text, "\n");
        $first = $break === false ? $text : substr($text, 0, $break);
        $rest = $break === false ? '' : trim(substr($text, $break + 1));

        // Quotes and dashes around a headline are dropped (multibyte-safe).
        $title = trim((string) preg_replace('/^[\s"\'“”«»:\-–—]+|[\s"\'“”«»:\-–—]+$/u', '', $first));
        if ($title === '') {
            $title = trim($first);
        }
        if (mb_strlen($title) > 190) {
            $title = Str::limit($title, 180, '…', preserveWords: true);
        }

        $paragraphs = array_values(array_filter(
            array_map('trim', preg_split("/\n{2,}/", $rest) ?: []),
            fn (string $paragraph): bool => $paragraph !== '',
        ));
        $summary = $paragraphs[0] ?? null;

        return [
            'title' => $title,
            'summary' => $summary !== null ? Str::limit(str_replace("\n", ' ', $summary), 300, '…', preserveWords: true) : null,
            'paragraphs' => $paragraphs,
        ];
    }

    /**
     * The story's first sixty normalized words.
     */
    public static function fingerprint(string $text): string
    {
        $words = array_filter(explode(' ', SearchText::normalize($text)), fn (string $w): bool => mb_strlen($w) > 1);

        return implode(' ', array_slice(array_values($words), 0, 60));
    }

    /** Share of words two fingerprints have in common (0 to 1). */
    public static function similarity(string $a, string $b): float
    {
        $wordsA = array_unique(array_filter(explode(' ', $a)));
        $wordsB = array_unique(array_filter(explode(' ', $b)));
        if ($wordsA === [] || $wordsB === []) {
            return 0.0;
        }

        return count(array_intersect($wordsA, $wordsB)) / count(array_unique([...$wordsA, ...$wordsB]));
    }

    /**
     * Rich-editor JSON (ADR-0012) for the paragraphs, line breaks kept, and
     * an optional gallery of library photos.
     *
     * @param  list<string>  $paragraphs
     * @param  list<int>  $gallery
     * @return array<string, mixed>
     */
    public static function body(array $paragraphs, array $gallery = []): array
    {
        $content = [];
        foreach ($paragraphs as $paragraph) {
            $nodes = [];
            foreach (explode("\n", $paragraph) as $i => $line) {
                if ($i > 0) {
                    $nodes[] = ['type' => 'hardBreak'];
                }
                if ($line !== '') {
                    $nodes[] = ['type' => 'text', 'text' => $line];
                }
            }
            $content[] = ['type' => 'paragraph', 'content' => $nodes];
        }

        if ($gallery !== []) {
            $content[] = ['type' => 'customBlock', 'attrs' => ['id' => 'gallery', 'config' => ['media_ids' => $gallery, 'caption' => '']]];
        }

        return ['type' => 'doc', 'content' => $content];
    }
}
