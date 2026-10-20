<?php

namespace App\Domain\Translation\Support;

use App\Domain\Translation\Models\UiTranslation;
use App\Domain\Translation\Models\UiTranslationRevision;
use Illuminate\Support\Collection;

/**
 * The rows of the translations grid for one language (ADR-0033): every
 * English key with its text in that language, its context, and whether it
 * is missing or English changed after it was last translated.
 */
final class TranslationRows
{
    public const TRANSLATED = 'translated';

    public const MISSING = 'missing';

    public const CHANGED = 'changed';

    public function __construct(private readonly TranslationCatalog $catalog) {}

    /**
     * @return Collection<string, array{key: string, group: string, english: string, text: string, status: string, context: ?string, screenshot: ?string}>
     */
    public function for(string $locale): Collection
    {
        $english = $this->catalog->values('en');
        $values = $this->catalog->values($locale);
        $lines = UiTranslation::query()->where('group', '*')->get(['key', 'context', 'screenshot_url'])->keyBy('key');
        $translatedFrom = $this->translatedFrom($locale);

        $rows = collect();
        foreach ($this->catalog->sourceKeys() as $key) {
            $text = (string) ($values[$key] ?? '');
            $status = match (true) {
                $text === '' => self::MISSING,
                isset($translatedFrom[$key]) && $translatedFrom[$key] !== ($english[$key] ?? '') => self::CHANGED,
                default => self::TRANSLATED,
            };
            $line = $lines->get($key);
            $rows->put($key, [
                'key' => $key,
                'group' => self::group($key),
                'english' => (string) ($english[$key] ?? ''),
                'text' => $text,
                'status' => $status,
                'context' => $line?->context,
                'screenshot' => $line?->screenshot_url,
            ]);
        }

        return $rows;
    }

    /** "events" for "events.registration.title". */
    public static function group(string $key): string
    {
        return explode('.', $key, 2)[0];
    }

    /**
     * The English text each key had when this language was last changed.
     *
     * @return array<string, ?string>
     */
    private function translatedFrom(string $locale): array
    {
        $latest = UiTranslationRevision::query()->where('locale', $locale)->selectRaw('max(id)')->groupBy('key');

        return UiTranslationRevision::query()->whereIn('id', $latest)->pluck('source_text', 'key')->all();
    }
}
