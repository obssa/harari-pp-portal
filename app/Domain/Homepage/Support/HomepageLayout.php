<?php

namespace App\Domain\Homepage\Support;

use App\Domain\Content\Enums\PostType;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\Translation;
use App\Domain\Content\Queries\PublicPosts;
use App\Domain\Homepage\Enums\SectionKey;
use App\Domain\Homepage\Models\HomepageSection;
use App\Domain\Media\Models\Gallery;
use App\Domain\Media\Models\GalleryItem;
use App\Domain\Media\Models\Video;
use App\Domain\Organisation\Models\Leader;
use App\Domain\Organisation\Models\League;
use App\Domain\Organisation\Queries\PublicDirectory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection as SupportCollection;

/**
 * The homepage as the builder arranged it (PLAN.md §4 Homepage, §7): the
 * sections in order, each with what it shows to readers of one language.
 * Sections with nothing to show are left out, except the hero, which falls
 * back to a welcome.
 */
final class HomepageLayout
{
    /** Core values offered when the section is first created (PLAN.md §4 item 8). */
    public const DEFAULT_VALUES = ['medemer', 'unity', 'democracy', 'sovereignty', 'inclusiveness', 'patriotism', 'solidarity'];

    public function __construct(private readonly PublicPosts $posts) {}

    /**
     * Every section of the current site in order, creating missing ones
     * with their defaults.
     *
     * @return Collection<int, HomepageSection>
     */
    public static function all(): Collection
    {
        $sections = HomepageSection::query()->orderBy('sort')->get();
        $present = $sections->map(fn (HomepageSection $s): string => $s->key->value)->all();
        $missing = array_filter(SectionKey::cases(), fn (SectionKey $key): bool => ! in_array($key->value, $present, true));
        if ($missing === []) {
            return $sections;
        }

        $sort = (int) $sections->max('sort');
        foreach ($missing as $key) {
            try {
                HomepageSection::query()->create([
                    'key' => $key,
                    'enabled' => $key->enabledByDefault(),
                    'sort' => ++$sort,
                    'settings' => self::defaultSettings($key),
                ]);
            } catch (QueryException) {
                // Created meanwhile by another request.
            }
        }

        return HomepageSection::query()->orderBy('sort')->get();
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultSettings(SectionKey $key): array
    {
        if ($key !== SectionKey::Values) {
            return [];
        }

        $items = [];
        foreach (self::DEFAULT_VALUES as $value) {
            $title = [];
            foreach (['en', 'am', 'om'] as $locale) {
                $title[$locale] = __("homepage.value.{$value}", [], $locale);
            }
            $items[] = ['title' => $title, 'text' => []];
        }

        return ['items' => $items];
    }

    /**
     * The sections readers of `$locale` see, with their content.
     *
     * @return list<array{section: HomepageSection, data: mixed}>
     */
    public function forLocale(string $locale): array
    {
        $shown = [];
        foreach (self::all() as $section) {
            if (! $section->enabled || ! $section->showsIn($locale)) {
                continue;
            }

            $data = $this->data($section, $locale);
            if ($data !== null) {
                $shown[] = ['section' => $section, 'data' => $data];
            }
        }

        return $shown;
    }

    private function data(HomepageSection $section, string $locale): mixed
    {
        $limit = $section->limit();

        return match ($section->key) {
            SectionKey::Ticker => $this->nonEmpty($section->curated_ids
                ? $this->posts->take($locale, $limit, ['ids' => $section->curated_ids])
                : $this->posts->take($locale, $limit)),
            SectionKey::Hero => $this->hero($section, $locale, $limit),
            SectionKey::Leadership => $this->leadership($section, $locale),
            SectionKey::LatestNews => $this->nonEmpty($this->posts->take($locale, $limit)),
            SectionKey::Press => $this->nonEmpty($this->posts->take($locale, $limit, ['types' => [PostType::PressRelease, PostType::Statement]])),
            SectionKey::Counters => $this->items($section, $locale, 'label', ['value']),
            SectionKey::Values => $this->items($section, $locale, 'title', [], ['text']),
            SectionKey::Leagues => $this->leagues($locale, $limit),
            SectionKey::Gallery => $this->gallery($locale, $limit),
            SectionKey::Video => $this->video($locale),
            SectionKey::Partners => $this->items($section, $locale, 'label', ['url']),
        };
    }

    /**
     * Hand-picked posts, else featured ones; an empty list means the welcome.
     *
     * @return SupportCollection<int, Post>
     */
    private function hero(HomepageSection $section, string $locale, int $limit): SupportCollection
    {
        if ($section->curated_ids) {
            $picked = $this->posts->take($locale, $limit, ['ids' => $section->curated_ids]);
            if ($picked->isNotEmpty()) {
                return $picked;
            }
        }

        return $this->posts->take($locale, $limit, ['featured' => true]);
    }

    /**
     * @return array{leader: Leader, translation: Translation, quote: string, link: ?string}|null
     */
    private function leadership(HomepageSection $section, string $locale): ?array
    {
        $leaderId = $section->setting('leader_id');
        $quote = HomepageSection::localized((array) $section->setting('quote', []), $locale);
        if (! is_string($leaderId) || $quote === '') {
            return null;
        }

        $leader = PublicDirectory::visible(Leader::query(), $locale)->with('portrait')->find($leaderId);
        $translation = $leader !== null ? PublicDirectory::shown($leader, $locale) : null;
        if ($leader === null || $translation === null) {
            return null;
        }

        $link = null;
        $postId = $section->setting('post_id');
        if (is_string($postId)) {
            $post = $this->posts->take($locale, 1, ['ids' => [$postId]])->first();
            $shown = $post !== null ? PublicPosts::shown($post, $locale) : null;
            $link = $shown !== null ? route('news.show', ['locale' => $shown->locale, 'slug' => $shown->slug]) : null;
        }

        return ['leader' => $leader, 'translation' => $translation, 'quote' => $quote, 'link' => $link];
    }

    /**
     * Published leagues, each with its latest post.
     *
     * @return list<array{league: League, post: ?Post}>|null
     */
    private function leagues(string $locale, int $limit): ?array
    {
        $leagues = PublicDirectory::visible(League::query(), $locale)->with(['logo', 'coverImage'])->orderBy('sort')->limit($limit)->get();
        $spotlight = [];
        foreach ($leagues as $league) {
            $spotlight[] = ['league' => $league, 'post' => $this->posts->linkedTo($locale, 'leagues', $league->id, 1)->first()];
        }

        return $spotlight === [] ? null : $spotlight;
    }

    /**
     * The featured video, otherwise the newest one.
     *
     * @return array{video: Video, translation: Translation}|null
     */
    private function video(string $locale): ?array
    {
        $video = PublicDirectory::visible(Video::query(), $locale)
            ->with('thumbnail')
            ->orderByDesc('is_featured')
            ->orderByDesc('recorded_on')
            ->orderByDesc('created_at')
            ->first();
        $translation = $video !== null ? PublicDirectory::shown($video, $locale) : null;

        return $video !== null && $translation !== null ? ['video' => $video, 'translation' => $translation] : null;
    }

    /**
     * The newest published album with its first photos.
     *
     * @return array{gallery: Gallery, translation: Translation, items: list<GalleryItem>}|null
     */
    private function gallery(string $locale, int $limit): ?array
    {
        $gallery = PublicDirectory::visible(Gallery::query(), $locale)
            ->whereHas('items')
            ->with(['items.media'])
            ->orderByDesc('taken_on')
            ->orderByDesc('created_at')
            ->first();
        $translation = $gallery !== null ? PublicDirectory::shown($gallery, $locale) : null;
        if ($gallery === null || $translation === null) {
            return null;
        }

        $items = array_values($gallery->items->take($limit)->all());

        return $items === [] ? null : ['gallery' => $gallery, 'translation' => $translation, 'items' => $items];
    }

    /**
     * The section's items with their texts in the reader's language (or its
     * fallbacks); items without the main text are left out.
     *
     * @param  list<string>  $plain  fields copied as they are
     * @param  list<string>  $optional  other per-language fields
     * @return list<array<string, string>>|null
     */
    private function items(HomepageSection $section, string $locale, string $main, array $plain = [], array $optional = []): ?array
    {
        $items = [];
        foreach ((array) $section->setting('items', []) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $text = HomepageSection::localized(is_array($item[$main] ?? null) ? $item[$main] : null, $locale);
            if ($text === '') {
                continue;
            }
            $row = [$main => $text];
            foreach ($plain as $field) {
                $row[$field] = (string) ($item[$field] ?? '');
            }
            foreach ($optional as $field) {
                $row[$field] = HomepageSection::localized(is_array($item[$field] ?? null) ? $item[$field] : null, $locale);
            }
            $items[] = $row;
        }

        return $items === [] ? null : $items;
    }

    /**
     * @param  SupportCollection<int, Post>  $posts
     * @return SupportCollection<int, Post>|null
     */
    private function nonEmpty(SupportCollection $posts): ?SupportCollection
    {
        return $posts->isEmpty() ? null : $posts;
    }
}
