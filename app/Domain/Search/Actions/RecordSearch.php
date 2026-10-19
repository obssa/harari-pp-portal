<?php

namespace App\Domain\Search\Actions;

use App\Domain\Search\Models\SearchTerm;
use App\Domain\Search\Support\SearchText;
use Illuminate\Database\QueryException;

/**
 * Counts a search for the search insights (PLAN.md §7): the normalized
 * words, the language and how many results it found.
 */
final class RecordSearch
{
    public function handle(string $query, string $locale, int $results): void
    {
        $term = mb_substr(implode(' ', SearchText::words($query)), 0, 100);
        if ($term === '') {
            return;
        }

        try {
            $row = SearchTerm::query()->firstOrCreate(
                ['locale' => $locale, 'term' => $term],
                ['searches' => 0, 'last_results' => $results, 'last_searched_at' => now()],
            );
            $row->forceFill(['last_results' => $results, 'last_searched_at' => now()])->save();
            $row->increment('searches');
        } catch (QueryException) {
            // Counting must never break the search page.
        }
    }
}
