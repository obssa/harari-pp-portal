<?php

namespace App\Services\Translation;

use App\Domain\Translation\Support\Locales;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * A large language model (Anthropic or OpenAI) asked for a formal
 * translation suitable for a political party, with the approved glossary
 * terms (ADR-0035). The answer must be a JSON list of the same length.
 */
final class LlmTranslationProvider implements TranslationProvider
{
    public const ANTHROPIC = 'https://api.anthropic.com/v1/messages';

    public const OPENAI = 'https://api.openai.com/v1/chat/completions';

    /** Defaults when no model is saved under Integrations. */
    public const DEFAULT_MODELS = ['anthropic' => 'claude-sonnet-5-5', 'openai' => 'gpt-4o-mini'];

    public function __construct(
        private readonly string $vendor,
        private readonly string $key,
        private readonly ?string $model = null,
    ) {}

    public function name(): string
    {
        return 'llm:'.$this->vendor;
    }

    public function translate(array $texts, string $from, string $to, array $glossary = []): array
    {
        $instructions = self::instructions($from, $to, $glossary);
        $payload = json_encode($texts, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $model = filled($this->model) ? (string) $this->model : (self::DEFAULT_MODELS[$this->vendor] ?? self::DEFAULT_MODELS['anthropic']);

        $response = $this->vendor === 'openai'
            ? Http::timeout(90)->withToken($this->key)->post(self::OPENAI, [
                'model' => $model,
                'temperature' => 0,
                'messages' => [['role' => 'system', 'content' => $instructions], ['role' => 'user', 'content' => $payload]],
            ])
            : Http::timeout(90)->withHeaders(['x-api-key' => $this->key, 'anthropic-version' => '2023-06-01'])->post(self::ANTHROPIC, [
                'model' => $model,
                'max_tokens' => 8000,
                'temperature' => 0,
                'system' => $instructions,
                'messages' => [['role' => 'user', 'content' => $payload]],
            ]);
        if (! $response->successful()) {
            throw new RuntimeException(__('translations.machine.error', ['message' => (string) $response->json('error.message', (string) $response->status())]));
        }

        $text = $this->vendor === 'openai'
            ? (string) $response->json('choices.0.message.content', '')
            : (string) $response->json('content.0.text', '');
        // Models sometimes wrap the list in a code fence.
        $text = trim((string) preg_replace('/^```(?:json)?|```$/m', '', trim($text)));
        $list = json_decode($text, true);
        if (! is_array($list) || count($list) !== count($texts) || ! array_is_list($list)) {
            throw new RuntimeException(__('translations.machine.error', ['message' => 'format']));
        }

        return array_map(fn (mixed $item): string => is_scalar($item) ? (string) $item : '', $list);
    }

    /**
     * @param  list<array{en: string, term: string}>  $glossary
     */
    public static function instructions(string $from, string $to, array $glossary): string
    {
        $lines = [
            'You translate the website of the Harari regional branch of the Prosperity Party (Ethiopia).',
            'Translate every string of the JSON list you receive from '.Locales::nativeName($from)." ({$from}) into ".Locales::nativeName($to)." ({$to}).",
            'Use a formal, respectful register suitable for an official political party website. Keep names of people and places.',
            'Tokens such as ⟦0⟧ stand for parts that must not change: keep every token exactly once and unchanged.',
            'Answer with a JSON list of strings only, in the same order and of the same length, with no other text.',
        ];
        if ($glossary !== []) {
            $lines[] = 'Always use these approved terms:';
            foreach ($glossary as $entry) {
                $lines[] = "- {$entry['en']} → {$entry['term']}";
            }
        }

        return implode("\n", $lines);
    }
}
