<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Api\Support\ApiPresenter;
use App\Domain\Content\Enums\PostType;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\PageTranslation;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostTranslation;
use App\Domain\Content\Queries\PublicPosts;
use App\Domain\Organisation\Queries\PublicDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * News and pages (scope `content`).
 */
final class ContentController extends ApiController
{
    public function __construct(private readonly ApiPresenter $present) {}

    public function posts(Request $request, PublicPosts $posts): JsonResponse
    {
        $locale = $this->locale();
        $page = $posts->paginate($locale, [
            'type' => PostType::tryFrom((string) $this->filter($request, 'type')),
            'category' => $this->filter($request, 'category'),
            'tag' => $this->filter($request, 'tag'),
        ], $this->perPage($request));

        return $this->page($page, fn (Post $post): ?array => ($shown = PublicPosts::shown($post, $locale)) !== null
            ? $this->present->post($post, $shown)
            : null);
    }

    public function post(string $key): JsonResponse
    {
        [$post, $shown] = $this->find(Post::query()->with(['featuredImage', 'categories.translations', 'tags.translations']), $key);
        abort_unless($shown instanceof PostTranslation, 404);

        return $this->one($this->present->post($post, $shown, full: true));
    }

    public function pages(Request $request): JsonResponse
    {
        $locale = $this->locale();
        $page = PublicDirectory::visible(Page::query(), $locale)
            ->with('featuredImage')
            ->orderBy('parent_id')->orderBy('sort')->orderBy('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->page($page, fn (Page $record): ?array => ($shown = PublicDirectory::shown($record, $locale)) instanceof PageTranslation
            ? $this->present->page($record, $shown)
            : null);
    }

    public function showPage(string $key): JsonResponse
    {
        [$page, $shown] = $this->find(Page::query()->with('featuredImage'), $key);
        abort_unless($shown instanceof PageTranslation, 404);
        $data = $this->present->page($page, $shown, full: true);
        abort_if($data === null, 404);

        return $this->one($data);
    }
}
