<?php

namespace App\Http\Controllers\Public;

use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostTranslation;
use App\Domain\Content\Support\BodyRenderer;
use App\Domain\Translation\Support\Locales;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

/**
 * Shows any language of a post, published or not, to whoever holds a signed
 * link (PLAN.md §7: tokenised preview that works for drafts). Links expire
 * after seven days; preview pages are never indexed or cached.
 */
final class PreviewController extends Controller
{
    public const DAYS = 7;

    public static function url(Post $post, string $locale): string
    {
        return URL::temporarySignedRoute('preview.post', now()->addDays(self::DAYS), [
            'post' => $post->getKey(),
            'language' => $locale,
        ]);
    }

    public function __invoke(string $post, string $language): Response
    {
        abort_unless(Locales::isSupported($language), 404);
        app()->setLocale($language);
        URL::defaults(['locale' => $language]);

        /** @var Post|null $record */
        $record = Post::query()->with(['translations', 'featuredImage', 'categories.translations', 'tags.translations'])->find($post);
        $translation = $record?->translation($language, fallback: false);
        abort_unless($record !== null && $translation instanceof PostTranslation, 404);

        return response()
            ->view('pages.news.show', [
                'post' => $record,
                'translation' => $translation,
                'body' => BodyRenderer::toHtml($translation->body),
                'alternates' => NewsController::alternates($record),
                'related' => collect(),
                'preview' => true,
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'private, no-store');
    }
}
