<?php

namespace App\Http\Controllers\Public;

use App\Domain\Content\Queries\PublicPosts;
use App\Domain\Content\Support\BodyRenderer;
use App\Domain\Organisation\Enums\OfficeType;
use App\Domain\Organisation\Models\Leader;
use App\Domain\Organisation\Models\Office;
use App\Domain\Organisation\Models\OfficeTranslation;
use App\Domain\Organisation\Queries\PublicDirectory;
use App\Domain\Social\Queries\SocialFeed;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The office directory (PLAN.md §4 Offices & Structures: filter by type,
 * search, office page with head, contacts, hours, map pin, sub-offices and
 * office news).
 */
final class OfficeController extends Controller
{
    public function index(Request $request): View
    {
        $locale = app()->getLocale();
        $type = OfficeType::tryFrom((string) $request->query('type'));
        $search = mb_substr(trim((string) $request->query('q')), 0, 80);

        $offices = PublicDirectory::visible(Office::query(), $locale)
            ->when($type, fn (Builder $q, OfficeType $t) => $q->where('type', $t->value))
            ->when($search !== '', fn (Builder $q) => $q->whereHas('translations', fn (Builder $t) => $t
                ->whereIn('locale', PublicPosts::languages($locale))
                ->where('name', 'like', '%'.addcslashes($search, '%_\\').'%')))
            ->with(['coverImage', 'parent.translations'])
            ->orderBy('sort')
            ->paginate(24)
            ->withQueryString();

        return view('pages.offices.index', ['offices' => $offices, 'type' => $type, 'search' => $search]);
    }

    public function show(string $slug, PublicPosts $posts): View
    {
        $locale = app()->getLocale();

        /** @var OfficeTranslation|null $translation */
        $translation = OfficeTranslation::query()->where('locale', $locale)->where('slug', $slug)->first();
        $office = $translation?->office()->with(['translations', 'coverImage', 'parent.translations', 'head.translations', 'head.portrait'])->first();
        abort_if($translation === null || $office === null || ! $translation->isLive(), 404);

        $children = PublicDirectory::visible($office->children()->getQuery(), $locale)->orderBy('sort')->get();
        $people = PublicDirectory::visible($office->leaders()->getQuery(), $locale)->with('portrait')->orderBy('rank')->get()
            ->reject(fn (Leader $leader): bool => $leader->id === $office->head_leader_id)
            ->values();

        return view('pages.offices.show', [
            'office' => $office,
            'translation' => $translation,
            'body' => BodyRenderer::toHtml($translation->body),
            'parent' => $office->parent !== null ? PublicDirectory::shown($office->parent, $locale) : null,
            'head' => $office->head !== null ? PublicDirectory::shown($office->head, $locale) : null,
            'children' => $children,
            'people' => $people,
            'posts' => $posts->linkedTo($locale, 'offices', $office->id),
            'social' => SocialFeed::latest(3, officeId: $office->id),
            'alternates' => PublicDirectory::alternates($office, 'offices.show', 'offices.index'),
            'image' => PublicDirectory::shareImage($office->coverImage, 'photo', "office-{$office->id}-{$locale}", $translation->name, $office->type->label()),
            'jsonLd' => [array_filter([
                '@type' => 'Organization',
                '@id' => route('offices.show', ['slug' => $translation->slug]).'#office',
                'name' => $translation->name,
                'description' => $translation->summary,
                'url' => route('offices.show', ['slug' => $translation->slug]),
                'telephone' => $office->phone,
                'email' => $office->email,
                'address' => $translation->address,
                'geo' => $office->hasMap() ? ['@type' => 'GeoCoordinates', 'latitude' => $office->latitude, 'longitude' => $office->longitude] : null,
                'parentOrganization' => ['@id' => url('/').'#organization'],
            ], fn (mixed $value): bool => $value !== null)],
        ]);
    }
}
