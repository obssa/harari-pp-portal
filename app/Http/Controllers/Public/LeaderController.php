<?php

namespace App\Http\Controllers\Public;

use App\Domain\Content\Queries\PublicPosts;
use App\Domain\Content\Support\BodyRenderer;
use App\Domain\Organisation\Enums\LeaderBody;
use App\Domain\Organisation\Models\Leader;
use App\Domain\Organisation\Models\LeaderTranslation;
use App\Domain\Organisation\Queries\PublicDirectory;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * The leadership directory (PLAN.md §4 About: grid by body, profile with
 * portrait, role, biography, term, speeches and statements, social links).
 */
final class LeaderController extends Controller
{
    public function index(): View
    {
        $locale = app()->getLocale();
        $leaders = PublicDirectory::visible(Leader::query(), $locale)
            ->with('portrait')
            ->orderBy('rank')
            ->get();

        $groups = [];
        foreach (LeaderBody::displayOrder() as $body) {
            $members = $leaders->filter(fn (Leader $leader): bool => $leader->body === $body)->values();
            if ($members->isNotEmpty()) {
                $groups[] = ['body' => $body, 'leaders' => $members];
            }
        }

        return view('pages.leaders.index', ['groups' => $groups]);
    }

    public function show(string $slug, PublicPosts $posts): View
    {
        $locale = app()->getLocale();

        /** @var LeaderTranslation|null $translation */
        $translation = LeaderTranslation::query()->where('locale', $locale)->where('slug', $slug)->first();
        $leader = $translation?->leader()->with(['translations', 'portrait', 'office.translations', 'league.translations'])->first();
        abort_if($translation === null || $leader === null || ! $translation->isLive(), 404);

        $url = route('leaders.show', ['slug' => $translation->slug]);
        $portrait = $leader->portrait;

        return view('pages.leaders.show', [
            'leader' => $leader,
            'translation' => $translation,
            'body' => BodyRenderer::toHtml($translation->body),
            'office' => $leader->office !== null ? PublicDirectory::shown($leader->office, $locale) : null,
            'league' => $leader->league !== null ? PublicDirectory::shown($leader->league, $locale) : null,
            'posts' => $posts->linkedTo($locale, 'leaders', $leader->id),
            'alternates' => PublicDirectory::alternates($leader, 'leaders.show', 'leaders.index'),
            'image' => PublicDirectory::shareImage($portrait, 'square', "leader-{$leader->id}-{$locale}", $translation->name, (string) $translation->position),
            'jsonLd' => [array_filter([
                '@type' => 'Person',
                '@id' => $url.'#person',
                'name' => $translation->name,
                'jobTitle' => $translation->position,
                'description' => $translation->summary,
                'url' => $url,
                'image' => $portrait !== null && $portrait->hasGeneratedConversion('square-640') ? $portrait->getUrl('square-640') : null,
                'sameAs' => array_values($leader->socialLinks()) ?: null,
                'memberOf' => ['@id' => url('/').'#organization'],
            ], fn (mixed $value): bool => $value !== null)],
        ]);
    }
}
