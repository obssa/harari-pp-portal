<?php

namespace App\Domain\Search\Engines;

use App\Domain\Search\Support\SearchText;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\DatabaseEngine;

/**
 * Scout's database driver (ADR-0002) matching word by word (ADR-0020):
 * every word of the query must appear in `search_text`, in any order. MySQL
 * full-text search is not used because it skips words shorter than three
 * letters, which leaves out many Amharic words, and shared hosting cannot
 * change that.
 */
final class WordMatchEngine extends DatabaseEngine
{
    /**
     * @param  EloquentBuilder<Model>  $query
     * @param  Builder<Model>  $builder
     * @param  array<int, string>  $columns
     * @param  array<int, string>  $prefixColumns
     * @param  array<int, string>  $fullTextColumns
     * @return EloquentBuilder<Model>
     */
    protected function addTextSearchConstraints($query, Builder $builder, array $columns, array $prefixColumns = [], array $fullTextColumns = [])
    {
        if (! in_array('search_text', $columns, true)) {
            return parent::addTextSearchConstraints($query, $builder, $columns, $prefixColumns, $fullTextColumns);
        }

        foreach (SearchText::words((string) $builder->query) as $word) {
            $query->where(
                $builder->model->qualifyColumn('search_text'),
                'like',
                '%'.addcslashes($word, '%_\\').'%',
            );
        }

        return $query;
    }
}
