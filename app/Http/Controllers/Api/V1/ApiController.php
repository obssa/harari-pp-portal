<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Content\Contracts\TranslatableModel;
use App\Domain\Content\Models\Translation;
use App\Domain\Content\Queries\PublicPosts;
use App\Domain\Organisation\Queries\PublicDirectory;
use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Shared pieces of the read-only public API (ADR-0056): one record by id or
 * slug, pages of records, and the same envelope for every answer.
 */
abstract class ApiController extends Controller
{
    protected function locale(): string
    {
        return app()->getLocale();
    }

    protected function perPage(Request $request): int
    {
        $asked = (int) $request->query('per_page', '0');

        return $asked > 0 ? min($asked, (int) config('portal.api.max_per_page')) : (int) config('portal.api.per_page');
    }

    /**
     * @template TModel of Model
     *
     * @param  LengthAwarePaginator<int, TModel>  $page
     * @param  Closure(TModel): ?array<string, mixed>  $present
     */
    protected function page(LengthAwarePaginator $page, Closure $present): JsonResponse
    {
        $data = [];
        foreach ($page->items() as $record) {
            $item = $present($record);
            if ($item !== null) {
                $data[] = $item;
            }
        }

        return response()->json([
            'data' => $data,
            'meta' => [
                'lang' => $this->locale(),
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
            'links' => [
                'next' => $page->nextPageUrl(),
                'prev' => $page->previousPageUrl(),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function one(array $data): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => ['lang' => $this->locale()]]);
    }

    /**
     * The live record with this id or slug and the translation a reader of
     * the request language sees, with all its languages loaded.
     *
     * @template TModel of Model&TranslatableModel
     *
     * @param  Builder<TModel>  $query
     * @return array{0: TModel, 1: Translation}
     */
    protected function find(Builder $query, string $key): array
    {
        $locale = $this->locale();
        $visible = PublicDirectory::visible($query, $locale);

        $record = Str::isUlid($key) ? (clone $visible)->whereKey(strtolower($key))->first() : null;
        $record ??= $visible->whereHas('translations', fn (Builder $q) => $q
            ->where('slug', $key)
            ->whereIn('locale', PublicPosts::languages($locale)))
            ->first();

        abort_if($record === null, 404);
        $record->load('translations');
        $shown = PublicDirectory::shown($record, $locale);
        abort_if($shown === null, 404);

        return [$record, $shown];
    }

    /**
     * A string from the query, or null when it is missing or not a string.
     */
    protected function filter(Request $request, string $name): ?string
    {
        $value = $request->query($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
