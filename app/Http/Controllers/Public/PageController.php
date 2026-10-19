<?php

namespace App\Http\Controllers\Public;

use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\PageTranslation;
use App\Domain\Content\Support\BodyRenderer;
use App\Domain\Translation\Support\Locales;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * Public pages at /{lang}/{path} (PLAN.md §3), e.g. /en/about/history. The
 * last segment identifies the page; a path whose parents do not match is
 * redirected to the page's real address.
 */
final class PageController extends Controller
{
    public function __invoke(string $path): Response|RedirectResponse
    {
        $locale = app()->getLocale();
        $slug = (string) last(explode('/', $path));

        /** @var PageTranslation|null $translation */
        $translation = PageTranslation::query()->where('locale', $locale)->where('slug', $slug)->first();
        $page = $translation?->page()->with(['translations', 'featuredImage', 'children.translations'])->first();
        abort_if($translation === null || $page === null || ! $translation->isLive(), 404);

        $canonical = $page->path($locale);
        abort_if($canonical === null, 404);
        if ($canonical !== $path) {
            return redirect()->route('page.show', ['path' => $canonical], 301);
        }

        $lineage = $page->lineage();

        return response()->view('pages.page', [
            'page' => $page,
            'translation' => $translation,
            'body' => BodyRenderer::toHtml($translation->body),
            'alternates' => self::alternates($page),
            'breadcrumbs' => array_values(array_filter(array_map(
                fn (Page $ancestor): ?array => ($t = $ancestor->translation($locale, fallback: false)) && $ancestor->path($locale)
                    ? ['title' => $t->title, 'url' => route('page.show', ['path' => $ancestor->path($locale)])]
                    : null,
                array_slice($lineage, 0, -1),
            ))),
            'children' => $page->children
                ->sortBy('sort')
                ->map(fn (Page $child): ?array => ($t = $child->translation($locale, fallback: false)) instanceof PageTranslation
                    && $t->isLive() && ($childPath = $child->path($locale)) !== null
                        ? ['title' => $t->title, 'excerpt' => $t->excerpt, 'url' => route('page.show', ['path' => $childPath])]
                        : null)
                ->filter()
                ->values(),
        ]);
    }

    /**
     * The page in each language it is live in; elsewhere that language's home.
     *
     * @return array<string, string>
     */
    public static function alternates(Page $page): array
    {
        $urls = [];
        foreach (Locales::all() as $locale) {
            $translation = $page->translation($locale, fallback: false);
            $path = $page->path($locale);
            $urls[$locale] = $translation instanceof PageTranslation && $translation->isLive() && $path !== null
                ? route('page.show', ['locale' => $locale, 'path' => $path])
                : route('home', ['locale' => $locale]);
        }

        return $urls;
    }
}
