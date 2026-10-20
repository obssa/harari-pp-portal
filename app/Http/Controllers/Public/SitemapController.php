<?php

namespace App\Http\Controllers\Public;

use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\PageTranslation;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostTranslation;
use App\Domain\Documents\Models\Document;
use App\Domain\Events\Models\Event;
use App\Domain\Media\Models\Gallery;
use App\Domain\Media\Models\Video;
use App\Domain\Organisation\Models\Leader;
use App\Domain\Organisation\Models\League;
use App\Domain\Organisation\Models\Office;
use App\Domain\Organisation\Queries\PublicDirectory;
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
        foreach (['home', 'news.index', 'leaders.index', 'offices.index', 'leagues.index', 'structure', 'gallery.index', 'videos.index', 'events.index', 'documents.index', 'documents.notices'] as $route) {
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

        $pages = Page::query()->with('translations')->get();
        foreach ($pages as $page) {
            $alternates = [];
            foreach (Locales::all() as $locale) {
                $translation = $page->translation($locale, fallback: false);
                $path = $page->path($locale);
                if ($translation instanceof PageTranslation && $translation->isLive() && ! $translation->noindex && $path !== null) {
                    $alternates[$locale] = route('page.show', ['locale' => $locale, 'path' => $path]);
                }
            }

            if (isset($alternates[$language])) {
                $entries[] = [
                    'url' => $alternates[$language],
                    'lastmod' => $page->translation($language, fallback: false)?->updated_at?->toAtomString(),
                    'alternates' => $alternates,
                ];
            }
        }

        $entries = [
            ...$entries,
            ...PublicDirectory::sitemap(Leader::query()->with('translations')->get(), $language, 'leaders.show'),
            ...PublicDirectory::sitemap(Office::query()->with('translations')->get(), $language, 'offices.show'),
            ...PublicDirectory::sitemap(League::query()->with('translations')->get(), $language, 'leagues.show'),
            ...PublicDirectory::sitemap(Gallery::query()->with('translations')->get(), $language, 'gallery.show'),
            ...PublicDirectory::sitemap(Video::query()->with('translations')->get(), $language, 'videos.show'),
            ...PublicDirectory::sitemap(Event::query()->with('translations')->get(), $language, 'events.show'),
            ...PublicDirectory::sitemap(Document::query()->publicAccess()->with('translations')->get(), $language, 'documents.show'),
        ];

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
