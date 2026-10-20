<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Concerns\BelongsToSite;
use App\Domain\Translation\Support\Locales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Throwable;

/**
 * A menu in one place of the site (header, footer columns, utility bar),
 * stored as a small tree of items (PLAN.md §7 Menus, ADR-0016). Each item
 * has a label per language and points at a page, a post, the news listing
 * or an outside address.
 *
 * @property int $id
 * @property string $location
 * @property list<array<string, mixed>> $items
 */
class Menu extends Model
{
    use BelongsToSite;
    use LogsActivity;

    public const LOCATIONS = ['header', 'footer', 'utility'];

    public const TYPES = ['page', 'post', 'news', 'leaders', 'offices', 'leagues', 'structure', 'gallery', 'videos', 'live', 'events', 'documents', 'notices', 'projects', 'achievements', 'contact', 'url'];

    /** Items per level, so menus stay usable on phones. */
    public const MAX_ITEMS = 12;

    protected $fillable = ['location', 'items', 'updated_by'];

    protected function casts(): array
    {
        return ['items' => 'array'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::forgetCache());
        static::deleted(fn () => self::forgetCache());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('content')->logOnly(['location', 'items'])->logOnlyDirty();
    }

    public static function forgetCache(): void
    {
        foreach (self::LOCATIONS as $location) {
            foreach (Locales::all() as $locale) {
                Cache::forget("menu:{$location}:{$locale}");
            }
        }
    }

    /**
     * Ready-to-render items of a location in a language: label and URL, with
     * children; items whose target is not live in that language are left out.
     *
     * @return list<array{label: string, url: string, external: bool, current: string|null, children: list<array{label: string, url: string, external: bool, current: string|null}>}>
     */
    public static function resolved(string $location, string $locale): array
    {
        try {
            return Cache::remember("menu:{$location}:{$locale}", 600, function () use ($location, $locale): array {
                $menu = self::query()->where('location', $location)->first();

                return $menu === null ? [] : self::resolveItems($menu->items, $locale, depth: 0);
            });
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  array<int, mixed>  $items
     * @return list<array<string, mixed>>
     */
    private static function resolveItems(array $items, string $locale, int $depth): array
    {
        $resolved = [];
        foreach (array_slice($items, 0, self::MAX_ITEMS) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $target = self::target($item, $locale);
            $label = self::label($item, $locale);
            if ($target === null || $label === '') {
                continue;
            }

            $resolved[] = [
                'label' => $label,
                'url' => $target['url'],
                'external' => $target['external'],
                'current' => $target['route'],
                'children' => $depth === 0 ? self::resolveItems((array) ($item['children'] ?? []), $locale, 1) : [],
            ];
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function label(array $item, string $locale): string
    {
        $labels = (array) ($item['labels'] ?? []);
        foreach (Locales::fallbackChain($locale) as $candidate) {
            if (filled($labels[$candidate] ?? null)) {
                return trim((string) $labels[$candidate]);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{url: string, external: bool, route: string|null}|null
     */
    private static function target(array $item, string $locale): ?array
    {
        return match ($item['type'] ?? null) {
            'news' => ['url' => route('news.index', ['locale' => $locale]), 'external' => false, 'route' => 'news.*'],
            'leaders', 'offices', 'leagues' => ['url' => route($item['type'].'.index', ['locale' => $locale]), 'external' => false, 'route' => $item['type'].'.*'],
            'structure' => ['url' => route('structure', ['locale' => $locale]), 'external' => false, 'route' => 'structure'],
            'gallery' => ['url' => route('gallery.index', ['locale' => $locale]), 'external' => false, 'route' => 'gallery.*'],
            'videos' => ['url' => route('videos.index', ['locale' => $locale]), 'external' => false, 'route' => 'videos.*'],
            'live' => ['url' => route('videos.live', ['locale' => $locale]), 'external' => false, 'route' => 'videos.live'],
            'events' => ['url' => route('events.index', ['locale' => $locale]), 'external' => false, 'route' => 'events.*'],
            'documents' => ['url' => route('documents.index', ['locale' => $locale]), 'external' => false, 'route' => 'documents.index'],
            'notices' => ['url' => route('documents.notices', ['locale' => $locale]), 'external' => false, 'route' => 'documents.notices'],
            'projects' => ['url' => route('projects.index', ['locale' => $locale]), 'external' => false, 'route' => 'projects.index'],
            'achievements' => ['url' => route('projects.achievements', ['locale' => $locale]), 'external' => false, 'route' => 'projects.achievements'],
            'contact' => ['url' => route('contact', ['locale' => $locale]), 'external' => false, 'route' => 'contact'],
            'page' => self::pageTarget((string) ($item['page_id'] ?? ''), $locale),
            'post' => self::postTarget((string) ($item['post_id'] ?? ''), $locale),
            'url' => self::urlTarget((string) ($item['url'] ?? '')),
            default => null,
        };
    }

    /**
     * @return array{url: string, external: bool, route: string|null}|null
     */
    private static function pageTarget(string $id, string $locale): ?array
    {
        $page = Page::query()->with('translations')->find($id);
        $translation = $page?->translation($locale, fallback: false);
        $path = $page?->path($locale);

        return $translation instanceof PageTranslation && $translation->isLive() && $path !== null
            ? ['url' => route('page.show', ['locale' => $locale, 'path' => $path]), 'external' => false, 'route' => null]
            : null;
    }

    /**
     * @return array{url: string, external: bool, route: string|null}|null
     */
    private static function postTarget(string $id, string $locale): ?array
    {
        $post = Post::query()->with('translations')->find($id);
        $translation = $post?->translation($locale, fallback: false);

        return $translation instanceof PostTranslation && $translation->isLive()
            ? ['url' => route('news.show', ['locale' => $locale, 'slug' => $translation->slug]), 'external' => false, 'route' => null]
            : null;
    }

    /**
     * @return array{url: string, external: bool, route: string|null}|null
     */
    private static function urlTarget(string $url): ?array
    {
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return ['url' => url($url), 'external' => false, 'route' => null];
        }

        return str_starts_with($url, 'https://') ? ['url' => $url, 'external' => true, 'route' => null] : null;
    }
}
