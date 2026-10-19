<?php

namespace App\Http\Controllers\Public;

use App\Domain\Search\Actions\RecordSearch;
use App\Domain\Search\SiteSearch;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * /{lang}/search?q= (PLAN.md §3 URL conventions, §4 Search, ADR-0020).
 * Answers are never cached or indexed; every search is counted for the
 * search insights.
 */
final class SearchController extends Controller
{
    public function __invoke(Request $request, SiteSearch $search, RecordSearch $record): Response
    {
        $locale = app()->getLocale();
        $query = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $type = (string) $request->query('type', '');
        $kind = array_key_exists($type, SiteSearch::KINDS) ? $type : null;

        $results = $search->search($query, $locale, $kind);
        $total = array_sum(array_map(fn ($found): int => $found->count(), $results));
        if ($kind === null && SiteSearch::searchable($query)) {
            $record->handle($query, $locale, $total);
        }

        return response()
            ->view('pages.search', [
                'query' => $query,
                'kind' => $kind,
                'results' => $results,
                'total' => $total,
                'searched' => SiteSearch::searchable($query),
            ])
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, follow');
    }
}
