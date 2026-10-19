<?php

namespace App\Domain\Translation\Support;

use App\Domain\Translation\Models\UiTranslation;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Finder\Finder;
use Throwable;

/**
 * Everything the portal knows about its UI strings: the shipped lang/*.json
 * files, the overrides edited in the admin (ui_translations), what is missing
 * per language and which keys the code uses. English is the source of truth.
 */
final class TranslationCatalog
{
    /** Keys from Laravel's own PHP group files, not our JSON catalogue. */
    private const FRAMEWORK_GROUPS = ['auth', 'pagination', 'passwords', 'validation'];

    /**
     * @return array<string, string>
     */
    public function fileValues(string $locale): array
    {
        $path = lang_path("{$locale}.json");
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        return array_map(fn (mixed $v): string => is_string($v) ? $v : '', (array) $decoded);
    }

    /**
     * Non-empty admin overrides for one locale.
     *
     * @return array<string, string>
     */
    public function databaseValues(string $locale): array
    {
        if (! $this->databaseAvailable()) {
            return [];
        }

        $values = [];
        foreach (UiTranslation::query()->where('group', '*')->get() as $row) {
            $value = $row->getTranslation($locale);
            if ($value !== null) {
                $values[$row->key] = $value;
            }
        }

        return $values;
    }

    /**
     * What the site shows for each key in this locale (before fallback).
     *
     * @return array<string, string>
     */
    public function values(string $locale, bool $withDatabase = true): array
    {
        $values = $this->fileValues($locale);
        if ($withDatabase) {
            $values = array_replace($values, $this->databaseValues($locale));
        }
        ksort($values);

        return $values;
    }

    /**
     * @return list<string>
     */
    public function sourceKeys(): array
    {
        $keys = array_keys($this->fileValues('en'));
        sort($keys);

        return $keys;
    }

    /**
     * @return list<string>
     */
    public function missing(string $locale, bool $withDatabase = true): array
    {
        $values = $this->values($locale, $withDatabase);

        return array_values(array_filter(
            $this->sourceKeys(),
            fn (string $key): bool => ($values[$key] ?? '') === '',
        ));
    }

    /**
     * Keys a locale file has that English does not (left over after a rename).
     *
     * @return list<string>
     */
    public function orphans(string $locale): array
    {
        return array_values(array_diff(array_keys($this->fileValues($locale)), $this->sourceKeys()));
    }

    /**
     * Translated strings whose :placeholders or plural parts differ from English.
     *
     * @return list<string>
     */
    public function placeholderProblems(string $locale, bool $withDatabase = true): array
    {
        $english = $this->fileValues('en');
        $problems = [];

        foreach ($this->values($locale, $withDatabase) as $key => $value) {
            if ($value === '' || ! isset($english[$key])) {
                continue;
            }
            if (! self::placeholdersMatch($english[$key], $value)) {
                $problems[] = $key;
            }
        }

        return $problems;
    }

    /**
     * @return array{translated: int, total: int, percent: float}
     */
    public function coverage(string $locale, bool $withDatabase = true): array
    {
        $total = count($this->sourceKeys());
        $translated = $total - count($this->missing($locale, $withDatabase));

        return [
            'translated' => $translated,
            'total' => $total,
            'percent' => $total === 0 ? 100.0 : round($translated / $total * 100, 1),
        ];
    }

    /**
     * Literal translation keys used in code, e.g. __('nav.news'), with the
     * files that use them. Framework group keys (validation.*) are skipped.
     *
     * @param  list<string>  $paths
     * @return array<string, list<string>>
     */
    public function keysUsedInCode(array $paths): array
    {
        $existing = array_values(array_filter($paths, 'is_dir'));
        if ($existing === []) {
            return [];
        }

        $pattern = '/(?:__|trans|trans_choice|@lang|@choice|Lang::get|Lang::choice)\(\s*([\'"])([a-z0-9_]+(?:\.[a-z0-9_]+)+)\1/';
        $used = [];

        foreach ((new Finder)->files()->in($existing)->name('*.php') as $file) {
            preg_match_all($pattern, self::withoutComments($file->getFilename(), $file->getContents()), $matches);
            foreach ($matches[2] as $key) {
                if (in_array(explode('.', $key)[0], self::FRAMEWORK_GROUPS, true)) {
                    continue;
                }
                $used[$key][] = $file->getRelativePathname();
            }
        }

        ksort($used);

        return array_map(fn (array $files): array => array_values(array_unique($files)), $used);
    }

    /**
     * Every English placeholder must appear in the translation (a grammatical
     * suffix such as Oromo ":attributen" is fine), the translation must not
     * invent placeholders, and plural forms must keep the same number of parts.
     */
    public static function placeholdersMatch(string $english, string $translation): bool
    {
        preg_match_all('/:[A-Za-z_]+/', $english, $m);
        $wanted = array_values(array_unique($m[0]));
        usort($wanted, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($wanted as $placeholder) {
            if (! str_contains($translation, $placeholder)) {
                return false;
            }
        }

        preg_match_all('/:[A-Za-z_]+/', $translation, $found);
        foreach ($found[0] as $token) {
            $known = false;
            foreach ($wanted as $placeholder) {
                if (str_starts_with($token, $placeholder)) {
                    $known = true;
                    break;
                }
            }
            if (! $known) {
                return false;
            }
        }

        return substr_count($english, '|') === substr_count($translation, '|');
    }

    /**
     * Comments are not code: drop PHP and Blade comments before scanning so
     * examples and commented-out lines do not count as used keys.
     */
    private static function withoutComments(string $filename, string $source): string
    {
        if (str_ends_with($filename, '.blade.php')) {
            return (string) preg_replace('/\{\{--.*?--\}\}/s', '', $source);
        }

        $code = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $code .= is_array($token) ? $token[1] : $token;
        }

        return $code;
    }

    private function databaseAvailable(): bool
    {
        try {
            return Schema::hasTable((new UiTranslation)->getTable());
        } catch (Throwable) {
            return false;
        }
    }
}
