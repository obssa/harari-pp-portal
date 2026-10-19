<?php

namespace App\Domain\Organisation\Queries;

use App\Domain\Content\Contracts\TranslatableModel;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\Translation;
use App\Domain\Content\Queries\PublicPosts;
use App\Domain\Content\Support\OgImage;
use App\Domain\Media\Models\Media;
use App\Domain\Translation\Support\Locales;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * What the public directory shows of offices, leaders and leagues
 * (ADR-0017). Readers see each record in their language, or in the
 * language their language falls back to for content (Harari → Amharic,
 * ADR-0014), and only languages that are published.
 */
final class PublicDirectory
{
    /**
     * Records published in a language a reader of `$locale` can be shown.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function visible(Builder $query, string $locale): Builder
    {
        $languages = PublicPosts::languages($locale);

        return $query
            ->whereHas('translations', fn (Builder $q) => $q
                ->whereIn('locale', $languages)
                ->where('status', ContentStatus::Published->value)
                ->where('published_at', '<=', now()))
            ->with(['translations' => fn ($q) => $q->whereIn('locale', $languages)]);
    }

    /**
     * The live translation a reader of this language should see.
     */
    public static function shown(Model&TranslatableModel $record, string $locale): ?Translation
    {
        foreach (PublicPosts::languages($locale) as $candidate) {
            $translation = $record->translation($candidate, fallback: false);
            if ($translation !== null && self::live($translation)) {
                return $translation;
            }
        }

        return null;
    }

    public static function live(Translation $translation): bool
    {
        return method_exists($translation, 'isLive') && $translation->isLive();
    }

    /**
     * The record's address in every language it is live in; other languages
     * go to the listing.
     *
     * @return array<string, string>
     */
    public static function alternates(Model&TranslatableModel $record, string $showRoute, string $indexRoute): array
    {
        $urls = [];
        foreach (Locales::all() as $locale) {
            $translation = $record->translation($locale, fallback: false);
            $urls[$locale] = $translation !== null && self::live($translation)
                ? route($showRoute, ['locale' => $locale, 'slug' => $translation->slug])
                : route($indexRoute, ['locale' => $locale]);
        }

        return $urls;
    }

    /**
     * The record's photo in its largest copy, otherwise a generated card.
     */
    public static function shareImage(?Media $photo, string $preset, string $key, string $title, string $kicker): string
    {
        $widths = (array) config("portal.media.presets.{$preset}.widths", []);
        rsort($widths);
        foreach ($widths as $width) {
            if ($photo !== null && $photo->hasGeneratedConversion("{$preset}-{$width}")) {
                return $photo->getUrl("{$preset}-{$width}");
            }
        }

        return app(OgImage::class)->url($key, $title, $kicker);
    }

    /**
     * Sitemap entries of every record live in `$language`.
     *
     * @param  iterable<Model&TranslatableModel>  $records
     * @return list<array{url: string, lastmod: ?string, alternates: array<string, string>}>
     */
    public static function sitemap(iterable $records, string $language, string $showRoute): array
    {
        $entries = [];
        foreach ($records as $record) {
            $alternates = [];
            foreach (Locales::all() as $locale) {
                $translation = $record->translation($locale, fallback: false);
                if ($translation !== null && self::live($translation)) {
                    $alternates[$locale] = route($showRoute, ['locale' => $locale, 'slug' => $translation->slug]);
                }
            }

            if (isset($alternates[$language])) {
                $updated = $record->translation($language, fallback: false)?->getAttribute('updated_at');
                $entries[] = [
                    'url' => $alternates[$language],
                    'lastmod' => $updated instanceof DateTimeInterface ? $updated->format(DATE_ATOM) : null,
                    'alternates' => $alternates,
                ];
            }
        }

        return $entries;
    }
}
