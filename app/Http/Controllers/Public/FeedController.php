<?php

namespace App\Http\Controllers\Public;

use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostTranslation;
use App\Domain\Content\Support\BodyRenderer;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * RSS 2.0 and JSON Feed 1.1 per language (PLAN.md §4: RSS and JSON feeds
 * per language): the 20 newest live posts of exactly this language.
 */
final class FeedController extends Controller
{
    public const LIMIT = 20;

    public function rss(): Response
    {
        $locale = app()->getLocale();

        return response()
            ->view('feeds.rss', ['items' => $this->items($locale), 'locale' => $locale])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    public function json(): JsonResponse
    {
        $locale = app()->getLocale();

        $items = $this->items($locale)->map(fn (array $item): array => array_filter([
            'id' => $item['url'],
            'url' => $item['url'],
            'title' => $item['title'],
            'summary' => $item['summary'],
            'content_html' => $item['html'],
            'image' => $item['image'],
            'date_published' => $item['date']->toRfc3339String(),
            'tags' => $item['tags'],
            'language' => $locale,
        ], fn (mixed $value): bool => $value !== null && $value !== []))->values()->all();

        return response()->json([
            'version' => 'https://jsonfeed.org/version/1.1',
            'title' => __('site.name').' — '.__('news.title'),
            'home_page_url' => route('news.index'),
            'feed_url' => route('feed.json'),
            'language' => $locale,
            'items' => $items,
        ], 200, ['Content-Type' => 'application/feed+json; charset=UTF-8'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return Collection<int, array{url: string, title: string, summary: ?string, html: string, image: ?string, date: CarbonImmutable, tags: array<int, non-empty-string>}>
     */
    private function items(string $locale): Collection
    {
        return Post::query()
            ->publishedIn($locale)
            ->with(['translations' => fn ($q) => $q->where('locale', $locale), 'featuredImage', 'categories.translations'])
            ->get()
            ->map(function (Post $post) use ($locale): ?array {
                $translation = $post->translation($locale, fallback: false);
                if (! $translation instanceof PostTranslation || $translation->published_at === null) {
                    return null;
                }

                return [
                    'url' => route('news.show', ['slug' => $translation->slug]),
                    'title' => $translation->title,
                    'summary' => $translation->excerpt,
                    'html' => BodyRenderer::toHtml($translation->body),
                    'image' => $post->featuredImage?->hasGeneratedConversion('landscape-960') ? $post->featuredImage->getUrl('landscape-960') : null,
                    'date' => $translation->published_at,
                    'tags' => $post->categories->map(fn ($c): string => $c->name($locale))->reject(fn (string $name): bool => $name === '')->values()->all(),
                ];
            })
            ->filter()
            ->sortByDesc(fn (array $item) => $item['date'])
            ->take(self::LIMIT)
            ->values();
    }
}
