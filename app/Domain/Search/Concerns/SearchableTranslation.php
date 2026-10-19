<?php

namespace App\Domain\Search\Concerns;

use App\Domain\Search\Support\SearchText;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

/**
 * One language of a record that site search finds (ADR-0020). The text to
 * match is kept in `search_text` on every save; with the database driver
 * search reads that column, with Meilisearch it is what gets indexed.
 *
 * @property ?string $search_text
 */
trait SearchableTranslation
{
    use Searchable;

    /**
     * The texts that describe this language of the record.
     *
     * @return list<?string>
     */
    abstract public function searchableParts(): array;

    abstract public function isLive(): bool;

    public static function bootSearchableTranslation(): void
    {
        static::saving(function (Model $translation): void {
            /** @var Model&self $translation */
            $translation->search_text = SearchText::build($translation->searchableParts());
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'search_text' => (string) $this->search_text,
            'locale' => $this->getAttribute('locale'),
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return $this->isLive();
    }
}
