<?php

namespace App\Http\Controllers\Public;

use App\Domain\Content\Queries\PublicPosts;
use App\Domain\Content\Support\BodyRenderer;
use App\Domain\Organisation\Models\Leader;
use App\Domain\Organisation\Models\League;
use App\Domain\Organisation\Models\LeagueTranslation;
use App\Domain\Organisation\Queries\PublicDirectory;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Leagues and forums (PLAN.md §4: each a small site with its leadership,
 * news and contacts; events, galleries, documents and the join form arrive
 * with their modules).
 */
final class LeagueController extends Controller
{
    public function index(): View
    {
        $leagues = PublicDirectory::visible(League::query(), app()->getLocale())
            ->with(['logo', 'coverImage'])
            ->orderBy('sort')
            ->get();

        return view('pages.leagues.index', ['leagues' => $leagues]);
    }

    public function show(string $slug, PublicPosts $posts): View
    {
        $locale = app()->getLocale();

        /** @var LeagueTranslation|null $translation */
        $translation = LeagueTranslation::query()->where('locale', $locale)->where('slug', $slug)->first();
        $league = $translation?->league()->with(['translations', 'logo', 'coverImage', 'head.translations', 'head.portrait'])->first();
        abort_if($translation === null || $league === null || ! $translation->isLive(), 404);

        $people = PublicDirectory::visible($league->leaders()->getQuery(), $locale)->with('portrait')->orderBy('rank')->get()
            ->reject(fn (Leader $leader): bool => $leader->id === $league->head_leader_id)
            ->values();
        $url = route('leagues.show', ['slug' => $translation->slug]);

        return view('pages.leagues.show', [
            'league' => $league,
            'translation' => $translation,
            'body' => BodyRenderer::toHtml($translation->body),
            'head' => $league->head !== null ? PublicDirectory::shown($league->head, $locale) : null,
            'people' => $people,
            'posts' => $posts->linkedTo($locale, 'leagues', $league->id),
            'alternates' => PublicDirectory::alternates($league, 'leagues.show', 'leagues.index'),
            'image' => PublicDirectory::shareImage($league->coverImage, 'landscape', "league-{$league->id}-{$locale}", $translation->name, __('leagues.title')),
            'jsonLd' => [array_filter([
                '@type' => 'Organization',
                '@id' => $url.'#league',
                'name' => $translation->name,
                'description' => $translation->summary,
                'url' => $url,
                'email' => $league->email,
                'telephone' => $league->phone,
                'sameAs' => array_values($league->socialLinks()) ?: null,
                'parentOrganization' => ['@id' => url('/').'#organization'],
            ], fn (mixed $value): bool => $value !== null)],
        ]);
    }
}
