<?php

namespace App\Services\Translation;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google Cloud Translation (v2, API key). Amharic and Afaan Oromo are both
 * supported; Harari is not, and is never sent (ADR-0035).
 */
final class GoogleTranslationProvider implements TranslationProvider
{
    public const ENDPOINT = 'https://translation.googleapis.com/language/translate/v2';

    public function __construct(private readonly string $key) {}

    public function name(): string
    {
        return 'google';
    }

    public function translate(array $texts, string $from, string $to, array $glossary = []): array
    {
        $response = Http::timeout(30)->asJson()->post(self::ENDPOINT.'?key='.urlencode($this->key), [
            'q' => $texts,
            'source' => $from,
            'target' => $to,
            'format' => 'text',
        ]);
        if (! $response->successful()) {
            throw new RuntimeException(__('translations.machine.error', ['message' => (string) $response->json('error.message', (string) $response->status())]));
        }

        $out = [];
        foreach ((array) $response->json('data.translations', []) as $item) {
            $out[] = html_entity_decode((string) ($item['translatedText'] ?? ''), ENT_QUOTES | ENT_HTML5);
        }
        if (count($out) !== count($texts)) {
            throw new RuntimeException(__('translations.machine.error', ['message' => 'count']));
        }

        return $out;
    }
}
