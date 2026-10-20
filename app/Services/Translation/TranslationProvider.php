<?php

namespace App\Services\Translation;

/**
 * A machine translation service (PLAN.md §9: google, llm, libretranslate,
 * null; ADR-0035). Texts arrive with their placeholders replaced by tokens
 * such as ⟦0⟧, which must come back unchanged.
 */
interface TranslationProvider
{
    public function name(): string;

    /**
     * @param  list<string>  $texts
     * @param  list<array{en: string, term: string}>  $glossary  approved terms for the target language
     * @return list<string> in the same order
     */
    public function translate(array $texts, string $from, string $to, array $glossary = []): array;
}
