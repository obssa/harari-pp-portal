<?php

namespace App\Domain\Content\Concerns;

use App\Domain\Content\Models\Translation;
use App\Domain\Translation\Support\Locales;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One row per language in a `*_translations` table (PLAN.md §6, §10,
 * ADR-0011). Lookups follow the portal's fallback chain (har → am → en,
 * om → en) unless asked for one exact language.
 *
 * @template TTranslation of Translation
 *
 * @property-read Collection<int, TTranslation> $translations
 */
trait HasTranslations
{
    /**
     * @return class-string<TTranslation>
     */
    abstract public static function translationModel(): string;

    public static function translationForeignKey(): string
    {
        return Str::snake(class_basename(static::class)).'_id';
    }

    /**
     * @return HasMany<TTranslation, $this>
     */
    public function translations(): HasMany
    {
        /** @var HasMany<TTranslation, $this> */
        return $this->hasMany(static::translationModel(), static::translationForeignKey());
    }

    /**
     * The translation for this language, or the nearest one in the fallback
     * chain. Uses the loaded relation when there is one (no extra query).
     *
     * @return TTranslation|null
     */
    public function translation(?string $locale = null, bool $fallback = true): ?Translation
    {
        $locale ??= app()->getLocale();
        $byLocale = $this->loadedOrQueriedTranslations()->keyBy('locale');

        foreach ($fallback ? Locales::fallbackChain($locale) : [$locale] as $candidate) {
            if ($byLocale->has($candidate)) {
                return $byLocale->get($candidate);
            }
        }

        return null;
    }

    /**
     * The translation for this language or its fallbacks, otherwise the
     * record's first language in portal order (labels in the admin, where a
     * record written only in Amharic must still have a name).
     *
     * @return TTranslation|null
     */
    public function translationOrAny(?string $locale = null): ?Translation
    {
        if (($translation = $this->translation($locale)) !== null) {
            return $translation;
        }

        $byLocale = $this->loadedOrQueriedTranslations()->keyBy('locale');
        foreach (Locales::all() as $candidate) {
            if ($byLocale->has($candidate)) {
                return $byLocale->get($candidate);
            }
        }

        return null;
    }

    /**
     * Languages this record has been written in, in portal order.
     *
     * @return list<string>
     */
    public function translatedLocales(): array
    {
        $present = $this->loadedOrQueriedTranslations()->pluck('locale')->all();

        return array_values(array_filter(Locales::all(), fn (string $locale): bool => in_array($locale, $present, true)));
    }

    /**
     * Writes one language, creating or updating its row.
     *
     * @param  array<string, mixed>  $attributes
     * @return TTranslation
     */
    public function putTranslation(string $locale, array $attributes): Translation
    {
        /** @var TTranslation $translation */
        $translation = $this->translations()->firstOrNew(['locale' => $locale]);
        $translation->fill($attributes)->save();
        $this->unsetRelation('translations');

        return $translation;
    }

    /**
     * Eager-loads the translations of the current language and its fallbacks.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithTranslation(Builder $query, ?string $locale = null): Builder
    {
        $chain = Locales::fallbackChain($locale ?? app()->getLocale());

        return $query->with(['translations' => fn (HasMany $relation) => $relation->whereIn('locale', $chain)]);
    }

    /**
     * Records that have a translation in exactly this language.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeTranslatedIn(Builder $query, string $locale): Builder
    {
        return $query->whereHas('translations', fn (Builder $q) => $q->where('locale', $locale));
    }

    /**
     * @return Collection<int, TTranslation>
     */
    private function loadedOrQueriedTranslations(): Collection
    {
        /** @var Collection<int, TTranslation> */
        return $this->relationLoaded('translations') ? $this->getRelation('translations') : $this->translations()->get();
    }
}
