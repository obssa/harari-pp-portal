<?php

namespace App\Http\Controllers\Public;

use App\Domain\Content\Enums\PostType;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostTranslation;
use App\Domain\Content\Queries\PublicPosts;
use App\Domain\Content\Support\BodyRenderer;
use App\Domain\Translation\Support\Locales;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * News & Media (PLAN.md §4): the listing with filters and one post. Each
 * post page links to the same post in the other languages, or to the news
 * listing of a language the post is not written in.
 */
final class NewsController extends Controller
{
    public function index(Request $request, PublicPosts $posts): View
    {
        $locale = app()->getLocale();
        $type = PostType::tryFrom((string) $request->query('type'));
        $filters = [
            'type' => $type,
            'category' => is_string($request->query('category')) ? $request->query('category') : null,
            'tag' => is_string($request->query('tag')) ? $request->query('tag') : null,
        ];

        return view('pages.news.index', [
            'posts' => $posts->paginate($locale, $filters),
            'type' => $type,
            'filters' => $filters,
        ]);
    }

    public function show(string $slug): View
    {
        $locale = app()->getLocale();

        /** @var PostTranslation|null $translation */
        $translation = PostTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->first();

        $post = $translation?->post()->with(['translations', 'featuredImage', 'categories.translations', 'tags.translations'])->first();
        abort_if($translation === null || $post === null || ! $translation->isLive(), 404);

        return view('pages.news.show', [
            'post' => $post,
            'translation' => $translation,
            'body' => BodyRenderer::toHtml($translation->body),
            'alternates' => self::alternates($post),
            'related' => $this->related($post, $locale),
        ]);
    }

    /**
     * The post in every language it is live in; other languages go to their news listing.
     *
     * @return array<string, string>
     */
    public static function alternates(Post $post): array
    {
        $urls = [];
        foreach (Locales::all() as $locale) {
            $translation = $post->translation($locale, fallback: false);
            $urls[$locale] = $translation instanceof PostTranslation && $translation->isLive()
                ? route('news.show', ['locale' => $locale, 'slug' => $translation->slug])
                : route('news.index', ['locale' => $locale]);
        }

        return $urls;
    }

    /**
     * Up to three other live posts sharing a category, newest first.
     *
     * @return Collection<int, Post>
     */
    private function related(Post $post, string $locale): Collection
    {
        $categoryIds = $post->categories->modelKeys();
        if ($categoryIds === []) {
            return collect();
        }

        return Post::query()
            ->publishedIn($locale)
            ->whereKeyNot($post->getKey())
            ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $categoryIds))
            ->with(['translations' => fn ($q) => $q->where('locale', $locale), 'featuredImage'])
            ->latest('updated_at')
            ->limit(3)
            ->get();
    }
}
