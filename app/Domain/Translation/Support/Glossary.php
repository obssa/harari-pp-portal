<?php

namespace App\Domain\Translation\Support;

use App\Domain\Translation\Models\GlossaryTerm;
use Illuminate\Support\Collection;

/**
 * Which approved terms an English text uses, and whether a translation
 * follows them (ADR-0034). English terms are matched as whole words, case
 * aside; the approved term must appear in the translation as written.
 */
final class Glossary
{
    /** @var Collection<int, GlossaryTerm>|null */
    private ?Collection $terms = null;

    /**
     * @return list<array{en: string, term: ?string, notes: ?string, followed: bool}>
     */
    public function check(string $english, string $translation, string $locale): array
    {
        $found = [];
        foreach ($this->terms() as $entry) {
            $source = $entry->term('en');
            if ($source === null || preg_match('/(?<![\pL\pN])'.preg_quote($source, '/').'(?![\pL\pN])/iu', $english) !== 1) {
                continue;
            }
            $term = $entry->term($locale);
            $found[] = [
                'en' => $source,
                'term' => $term,
                'notes' => $entry->notes,
                'followed' => $term === null || $translation === '' || mb_stripos($translation, $term) !== false,
            ];
        }

        return $found;
    }

    /** Whether a translated text misses an approved term. */
    public function breaks(string $english, string $translation, string $locale): bool
    {
        foreach ($this->check($english, $translation, $locale) as $use) {
            if (! $use['followed']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, GlossaryTerm>
     */
    private function terms(): Collection
    {
        return $this->terms ??= GlossaryTerm::query()->get();
    }
}
