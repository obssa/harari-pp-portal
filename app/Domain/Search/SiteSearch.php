<?php

namespace App\Domain\Search;

use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\PageTranslation;
use App\Domain\Content\Models\PostTranslation;
use App\Domain\Content\Queries\PublicPosts;
use App\Domain\Events\Models\EventTranslation;
use App\Domain\Organisation\Models\LeaderTranslation;
use App\Domain\Organisation\Models\LeagueTranslation;
use App\Domain\Organisation\Models\OfficeTranslation;
use App\Domain\Search\Support\SearchText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Site search (PLAN.md §4 Search, ADR-0020): posts, pages, people, offices
 * and leagues published in the reader's language (and, for Harari, in
 * Amharic), each kind with its own list.
 */
final class SiteSearch
{
    /** @var array<string, array{model: class-string<PostTranslation|PageTranslation|LeaderTranslation|OfficeTranslation|LeagueTranslation|EventTranslation>, parent: string, order: string}> */
    public const KINDS = [
        'news' => ['model' => PostTranslation::class, 'parent' => 'post', 'order' => 'published_at'],
        'pages' => ['model' => PageTranslation::class, 'parent' => 'page', 'order' => 'title'],
        'people' => ['model' => LeaderTranslation::class, 'parent' => 'leader', 'order' => 'name'],
        'offices' => ['model' => OfficeTranslation::class, 'parent' => 'office', 'order' => 'name'],
        'leagues' => ['model' => LeagueTranslation::class, 'parent' => 'league', 'order' => 'name'],
        'events' => ['model' => EventTranslation::class, 'parent' => 'event', 'order' => 'title'],
    ];

    /** Results per kind on the overview; one kind alone shows more. */
    public const PER_KIND = 5;

    public const PER_KIND_ALONE = 30;

    public static function searchable(string $query): bool
    {
        return SearchText::words($query) !== [];
    }

    /**
     * @return array<string, Collection<int, Model>> kind => translations found
     */
    public function search(string $query, string $locale, ?string $kind = null): array
    {
        if (! self::searchable($query)) {
            return [];
        }

        $normalized = implode(' ', SearchText::words($query));
        $languages = PublicPosts::languages($locale);
        $results = [];

        foreach (self::KINDS as $name => $spec) {
            if ($kind !== null && $kind !== $name) {
                continue;
            }

            $model = $spec['model'];
            $found = $model::search($normalized)
                ->whereIn('locale', $languages)
                ->query(fn (Builder $q): Builder => $q
                    ->where('status', ContentStatus::Published->value)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now())
                    ->whereHas($spec['parent'])
                    ->with($spec['parent'])
                    ->orderBy($spec['order'], $spec['order'] === 'published_at' ? 'desc' : 'asc'))
                ->take($kind === null ? self::PER_KIND + 1 : self::PER_KIND_ALONE)
                ->get();

            $results[$name] = $found;
        }

        return $results;
    }

    /**
     * What a result shows: where it goes, its title, a short text and a label.
     *
     * @return array{url: string, title: string, summary: ?string, label: string, locale: string}|null
     */
    public static function present(Model $translation): ?array
    {
        $locale = (string) $translation->getAttribute('locale');
        $slug = (string) $translation->getAttribute('slug');

        return match (true) {
            $translation instanceof PostTranslation => [
                'url' => route('news.show', ['locale' => $locale, 'slug' => $slug]),
                'title' => $translation->title,
                'summary' => $translation->excerpt,
                'label' => $translation->post->type->label(),
                'locale' => $locale,
            ],
            $translation instanceof PageTranslation => ($path = $translation->page->path($locale)) === null ? null : [
                'url' => route('page.show', ['locale' => $locale, 'path' => $path]),
                'title' => $translation->title,
                'summary' => $translation->excerpt,
                'label' => __('search.kind.pages'),
                'locale' => $locale,
            ],
            $translation instanceof LeaderTranslation => [
                'url' => route('leaders.show', ['locale' => $locale, 'slug' => $slug]),
                'title' => $translation->name,
                'summary' => $translation->position ?: $translation->summary,
                'label' => $translation->leader->body->label(),
                'locale' => $locale,
            ],
            $translation instanceof OfficeTranslation => [
                'url' => route('offices.show', ['locale' => $locale, 'slug' => $slug]),
                'title' => $translation->name,
                'summary' => $translation->summary,
                'label' => $translation->office->type->label(),
                'locale' => $locale,
            ],
            $translation instanceof EventTranslation => [
                'url' => route('events.show', ['locale' => $locale, 'slug' => $slug]),
                'title' => $translation->title,
                'summary' => $translation->summary,
                'label' => __('search.kind.events'),
                'locale' => $locale,
            ],
            $translation instanceof LeagueTranslation => [
                'url' => route('leagues.show', ['locale' => $locale, 'slug' => $slug]),
                'title' => $translation->name,
                'summary' => $translation->summary,
                'label' => __('search.kind.leagues'),
                'locale' => $locale,
            ],
            default => null,
        };
    }
}
