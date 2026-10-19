<?php

namespace App\Domain\Content\Contracts;

use App\Domain\Content\Models\Translation;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A model with one row per language (ADR-0011), via the HasTranslations concern.
 */
interface TranslatableModel
{
    /**
     * @return class-string<Translation>
     */
    public static function translationModel(): string;

    public static function translationForeignKey(): string;

    /**
     * @return HasMany<covariant Translation, covariant \Illuminate\Database\Eloquent\Model>
     */
    public function translations(): HasMany;

    public function translation(?string $locale = null, bool $fallback = true): ?Translation;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function putTranslation(string $locale, array $attributes): Translation;

    /**
     * @return list<string>
     */
    public function translatedLocales(): array;
}
