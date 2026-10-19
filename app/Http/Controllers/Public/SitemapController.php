<?php

namespace App\Http\Controllers\Public;

use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostTranslation;
use App\Domain\Translation\Support\Locales;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Sitemaps (PLAN.md §11 SEO): an index pointing at one sitemap per
 * language, each listing that language's live pages with links to the same
 * page in the other languages (hreflang). Cached for an hour.
 */
final class SitemapController extends Controller
{
    public const TTL = 3600;

    public function index(): Response
    {
        $xml = Cache::remember('sitemap:index', self::TTL, fn (): string => view('feeds.sitemap-index', [
            'locales' => Locales::all(),
        ])->render());

        return $this->xml($xml);
    }

    public function language(string $language): Response
    {
        abort_unless(Locales::isSupported($language), 404);

        $xml = Cache::remember("sitemap:{$language}", self::TTL, fn (): string => view('feeds.sitemap', [
            'entries' => $this->entries($language),
        ])->render());

        return $this->xml($xml);
    }

    /**
     * @return list<array{url: string, lastmod: ?string, alternates: array<string, string>}>
     */
    private function entries(string $language): array
    {
        $entries = [];
        foreach (['home', 'news.index'] as $route) {
            $alternates = [];
            foreach (Locales::all() as $locale) {
                $alternates[$locale] = route($route, ['locale' => $locale]);
            }
            $entries[] = ['url' => $alternates[$language], 'lastmod' => null, 'alternates' => $alternates];
        }

        $posts = Post::query()
            ->publishedIn($language)
            ->with(['translations' => fn ($q) => $q->where('status', ContentStatus::Published->value)])
            ->get();

        foreach ($posts as $post) {
            $alternates = [];
            foreach ($post->translations as $translation) {
                /** @var PostTranslation $translation */
                if ($translation->isLive()) {
                    $alternates[$translation->locale] = route('news.show', ['locale' => $translation->locale, 'slug' => $translation->slug]);
                }
            }

            $own = $post->translation($language, fallback: false);
            if ($own === null || ! isset($alternates[$language])) {
                continue;
            }

            $entries[] = [
                'url' => $alternates[$language],
                'lastmod' => $own->updated_at?->toAtomString(),
                'alternates' => $alternates,
            ];
        }

        return $entries;
    }

    private function xml(string $body): Response
    {
        return response($body, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=0, s-maxage='.self::TTL,
        ]);
    }
}
