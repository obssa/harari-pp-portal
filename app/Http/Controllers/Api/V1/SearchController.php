<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Search\SiteSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Site search (scope `search`): the same results as /{lang}/search, all
 * kinds or one `kind`. API searches are not counted in the search insights.
 */
final class SearchController extends ApiController
{
    public function __invoke(Request $request, SiteSearch $search): JsonResponse
    {
        $query = mb_substr((string) $this->filter($request, 'q'), 0, 100);
        $kind = $this->filter($request, 'kind');
        if ($kind !== null && ! array_key_exists($kind, SiteSearch::KINDS)) {
            return response()->json(['message' => __('api.error.kind', ['kinds' => implode(', ', array_keys(SiteSearch::KINDS))])], 422);
        }

        $data = [];
        foreach ($search->search($query, $this->locale(), $kind) as $name => $found) {
            foreach ($found->take($kind === null ? SiteSearch::PER_KIND : SiteSearch::PER_KIND_ALONE) as $translation) {
                $item = SiteSearch::present($translation);
                if ($item !== null) {
                    $data[] = [
                        'kind' => $name,
                        'lang' => $item['locale'],
                        'title' => $item['title'],
                        'summary' => $item['summary'],
                        'label' => $item['label'],
                        'url' => $item['url'],
                    ];
                }
            }
        }

        return response()->json([
            'data' => $data,
            'meta' => ['lang' => $this->locale(), 'query' => $query, 'kind' => $kind, 'searched' => SiteSearch::searchable($query)],
        ]);
    }
}
