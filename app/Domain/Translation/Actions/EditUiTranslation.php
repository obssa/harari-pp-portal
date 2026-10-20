<?php

namespace App\Domain\Translation\Actions;

use App\Domain\Translation\Models\UiTranslation;
use App\Domain\Translation\Models\UiTranslationRevision;
use App\Domain\Translation\Support\Locales;
use App\Domain\Translation\Support\TranslationCatalog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Changes one language of one interface string (ADR-0033): only for keys
 * English has, only by people allowed to edit that language, only with the
 * placeholders English uses. Every change is kept with the English text it
 * was made from. An empty text removes the admin's version, so the shipped
 * text (or the fallback language) shows again.
 */
final class EditUiTranslation
{
    public function __construct(private readonly TranslationCatalog $catalog) {}

    public function handle(string $key, string $locale, ?string $text, User $actor, string $origin = 'editor'): void
    {
        abort_unless(in_array($locale, Locales::all(), true) && $actor->canEditTranslationsFor($locale), 403);

        $english = $this->catalog->values('en');
        if (! array_key_exists($key, $english)) {
            throw ValidationException::withMessages(['text' => __('translations.validation.unknown_key')]);
        }

        $text = $text !== null ? trim(str_replace("\r\n", "\n", $text)) : '';
        if (mb_strlen($text) > 5000) {
            throw ValidationException::withMessages(['text' => __('translations.validation.too_long')]);
        }
        if ($text !== '' && $locale !== 'en' && ! TranslationCatalog::placeholdersMatch($english[$key], $text)) {
            throw ValidationException::withMessages(['text' => __('translations.validation.placeholders', ['placeholders' => self::placeholders($english[$key])])]);
        }

        DB::transaction(function () use ($key, $locale, $text, $actor, $english, $origin): void {
            /** @var UiTranslation $line */
            $line = UiTranslation::query()->firstOrNew(['group' => '*', 'key' => $key]);
            $values = $line->text ?? [];
            if (($values[$locale] ?? '') === $text) {
                return;
            }
            $old = $this->catalog->values($locale)[$key] ?? null;

            if ($text === '') {
                unset($values[$locale]);
            } else {
                $values[$locale] = $text;
            }

            if ($values === []) {
                if ($line->exists) {
                    $line->delete();
                }
            } else {
                $line->text = $values;
                $line->updated_by = $actor->id;
                $line->save();
            }

            UiTranslationRevision::query()->create([
                'key' => $key,
                'locale' => $locale,
                'old_text' => $old,
                'new_text' => $text !== '' ? $text : null,
                'source_text' => $english[$key],
                'origin' => $origin,
                'user_id' => $actor->id,
            ]);
        });
    }

    /** The placeholders of a text, for the error message. */
    public static function placeholders(string $text): string
    {
        preg_match_all('/:[A-Za-z_]+/', $text, $matches);

        return implode(' ', array_unique($matches[0])) ?: '—';
    }
}
