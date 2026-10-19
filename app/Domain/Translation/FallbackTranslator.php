<?php

namespace App\Domain\Translation;

use App\Domain\Translation\Support\Locales;
use Illuminate\Translation\Translator;

/**
 * Laravel's translator with the portal's fallback chain (PLAN.md §6):
 * har → am → en, om → en, am → en.
 *
 * Two differences from the stock translator matter here:
 *  - JSON keys (all of our UI strings) fall back along the chain; Laravel
 *    itself only falls back for PHP group files.
 *  - Empty strings count as missing. Harari ships with every key present and
 *    empty, so without this the page would show raw keys.
 *
 * Strings served from a fallback locale are remembered for the request so
 * the admin and tests can see what is not translated yet.
 */
class FallbackTranslator extends Translator
{
    /** @var array<string, array<string, string>> requested locale => key => locale used */
    private array $fallbacksUsed = [];

    /**
     * @param  string  $key
     * @param  array<string, mixed>  $replace
     * @param  string|null  $locale
     * @param  bool  $fallback
     * @return string|array<mixed>
     */
    public function get($key, array $replace = [], $locale = null, $fallback = true)
    {
        $locale = $locale ?: $this->locale;
        $locales = $fallback ? Locales::fallbackChain($locale) : [$locale];

        foreach ($locales as $candidate) {
            $line = $this->jsonLine($key, $candidate);

            if ($line !== null) {
                $this->remember($locale, $key, $candidate);

                return $this->makeReplacements($line, $replace);
            }

            [$namespace, $group, $item] = $this->parseKey($key);
            $line = $this->getLine($namespace, $group, $candidate, $item, $replace);

            if (is_array($line) || (is_string($line) && $line !== '')) {
                $this->remember($locale, $key, $candidate);

                return $line;
            }
        }

        $missing = $this->handleMissingTranslationKey($key, $replace, $locale, $fallback);

        return $this->makeReplacements($missing, $replace);
    }

    /**
     * trans_choice() picks its locale before calling get(); Laravel jumps
     * straight to the single fallback locale. Walk the portal chain instead and
     * use the first locale that has the string (its plural rules apply).
     *
     * @param  string  $key
     * @param  string|null  $locale
     * @return string
     */
    protected function localeForChoice($key, $locale)
    {
        $locale = $locale ?: $this->locale;

        foreach (Locales::fallbackChain($locale) as $candidate) {
            if ($this->hasForLocale($key, $candidate)) {
                $this->remember($locale, $key, $candidate);

                return $candidate;
            }
        }

        return $locale;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function fallbacksUsed(): array
    {
        return $this->fallbacksUsed;
    }

    private function jsonLine(string $key, string $locale): ?string
    {
        $this->load('*', '*', $locale);
        $line = $this->loaded['*']['*'][$locale][$key] ?? null;

        return is_string($line) && $line !== '' ? $line : null;
    }

    private function remember(string $requested, string $key, string $used): void
    {
        if ($used !== $requested) {
            $this->fallbacksUsed[$requested][$key] = $used;
        }
    }
}
