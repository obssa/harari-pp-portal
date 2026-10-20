<?php

namespace App\Services\Translation;

use App\Domain\Translation\Models\GlossaryTerm;
use App\Domain\Translation\Support\TranslationCatalog;
use App\Settings\IntegrationSettings;
use RuntimeException;

/**
 * Machine translation for Amharic and Afaan Oromo only (PLAN.md §6: never
 * Harari, ADR-0035). Off until a Super Admin chooses a driver and saves its
 * key. Placeholders are hidden behind tokens on the way out and checked on
 * the way back; a text that comes back without them is dropped.
 */
final class MachineTranslator
{
    /** Languages a machine may translate into. */
    public const TARGETS = ['am', 'om'];

    /** Texts sent per request. */
    public const BATCH = 40;

    public function __construct(private readonly IntegrationSettings $settings) {}

    public function enabledFor(string $locale): bool
    {
        return in_array($locale, self::TARGETS, true) && $this->provider() !== null;
    }

    public function provider(): ?TranslationProvider
    {
        return match ($this->settings->mt_driver) {
            'google' => filled($this->settings->google_translate_api_key) ? new GoogleTranslationProvider((string) $this->settings->google_translate_api_key) : null,
            'llm' => filled($this->settings->llm_api_key) ? new LlmTranslationProvider($this->settings->llm_provider, (string) $this->settings->llm_api_key, $this->settings->llm_model) : null,
            default => null,
        };
    }

    /**
     * @param  array<array-key, string>  $texts
     * @return array<array-key, ?string> null where the translation was unusable
     */
    public function translate(array $texts, string $from, string $to): array
    {
        if (! in_array($to, self::TARGETS, true)) {
            throw new RuntimeException(__('translations.machine.not_for', ['language' => $to]));
        }
        $provider = $this->provider() ?? throw new RuntimeException(__('translations.machine.off'));

        $glossary = [];
        foreach (GlossaryTerm::query()->get() as $entry) {
            if ($entry->term($from) !== null && $entry->term($to) !== null) {
                $glossary[] = ['en' => (string) $entry->term($from), 'term' => (string) $entry->term($to)];
            }
        }

        $out = [];
        $pending = [];
        foreach ($texts as $key => $text) {
            if (trim($text) === '') {
                $out[$key] = $text;
            } else {
                $pending[$key] = $text;
            }
        }

        foreach (array_chunk($pending, self::BATCH, preserve_keys: true) as $chunk) {
            $protected = [];
            $tokens = [];
            foreach ($chunk as $key => $text) {
                [$protected[$key], $tokens[$key]] = self::protect($text);
            }
            $answers = $provider->translate(array_values($protected), $from, $to, $glossary);
            foreach (array_keys($chunk) as $i => $key) {
                $restored = self::restore((string) ($answers[$i] ?? ''), $tokens[$key]);
                $out[$key] = $restored !== null && trim($restored) !== '' && TranslationCatalog::placeholdersMatch($chunk[$key], $restored) ? $restored : null;
            }
        }

        return $out;
    }

    /**
     * Translates the words of a rich text body and keeps its structure,
     * marks and blocks.
     *
     * @param  array<mixed>  $body
     * @return array<mixed>
     */
    public function translateBody(array $body, string $from, string $to): array
    {
        $texts = [];
        self::collect($body, $texts);
        if ($texts === []) {
            return $body;
        }
        $translated = $this->translate($texts, $from, $to);
        $index = 0;

        return self::replace($body, $translated, $index);
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private static function protect(string $text): array
    {
        $tokens = [];
        $protected = (string) preg_replace_callback('/:[A-Za-z_]+/', function (array $m) use (&$tokens): string {
            $tokens[] = $m[0];

            return '⟦'.(count($tokens) - 1).'⟧';
        }, $text);

        return [$protected, $tokens];
    }

    /**
     * @param  list<string>  $tokens
     */
    private static function restore(string $text, array $tokens): ?string
    {
        foreach ($tokens as $i => $token) {
            if (substr_count($text, "⟦{$i}⟧") !== 1) {
                return null;
            }
            $text = str_replace("⟦{$i}⟧", $token, $text);
        }

        return $text;
    }

    /**
     * @param  array<mixed>  $node
     * @param  list<string>  $texts
     */
    private static function collect(array $node, array &$texts): void
    {
        if (($node['type'] ?? null) === 'text' && is_string($node['text'] ?? null) && trim($node['text']) !== '') {
            $texts[] = $node['text'];
        }
        foreach ((array) ($node['content'] ?? []) as $child) {
            if (is_array($child)) {
                self::collect($child, $texts);
            }
        }
    }

    /**
     * @param  array<mixed>  $node
     * @param  array<array-key, ?string>  $translated
     * @return array<mixed>
     */
    private static function replace(array $node, array $translated, int &$index): array
    {
        if (($node['type'] ?? null) === 'text' && is_string($node['text'] ?? null) && trim($node['text']) !== '') {
            $node['text'] = $translated[$index] ?? $node['text'];
            $index++;
        }
        if (isset($node['content']) && is_array($node['content'])) {
            foreach ($node['content'] as $i => $child) {
                if (is_array($child)) {
                    $node['content'][$i] = self::replace($child, $translated, $index);
                }
            }
        }

        return $node;
    }
}
