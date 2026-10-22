<?php

namespace App\Domain\Homepage\Support;

use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\Post;
use App\Domain\Organisation\Models\Leader;
use App\Support\PublicLinks;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Where a carousel button goes (ADR-0070), stored as one string:
 *
 *   route:membership.apply   a page of the site, in the visitor's language
 *   leader:<id>              a leader's profile
 *   page:<id>, post:<id>     a page or a story, where it is published
 *   url:https://…            any web address, or a path on this site (/en/…)
 */
final class SlideLink
{
    /** Site pages a button may open: route name => label key. */
    public const ROUTES = [
        'membership.apply' => 'nav.join',
        'get-involved' => 'nav.get_involved',
        'news.index' => 'nav.news',
        'leaders.index' => 'leaders.title',
        'structure' => 'structure.title',
        'offices.index' => 'offices.title',
        'leagues.index' => 'leagues.title',
        'events.index' => 'events.title',
        'projects.index' => 'projects.title',
        'documents.index' => 'documents.title',
        'gallery.index' => 'gallery.title',
        'social' => 'social.hub.title',
        'newsletter.create' => 'nav.newsletter',
        'donations.create' => 'donations.title',
        'contact' => 'nav.contact',
        'home' => 'nav.home',
    ];

    /** Kinds of link, for the admin form. */
    public const KINDS = ['route', 'leader', 'page', 'post', 'url'];

    /**
     * @return array<string, string> route name => label
     */
    public static function routeOptions(): array
    {
        $options = [];
        foreach (self::ROUTES as $route => $label) {
            if (Route::has($route)) {
                $options[$route] = __($label);
            }
        }

        return $options;
    }

    /**
     * @return array{0: string, 1: string} kind and value
     */
    public static function split(?string $link): array
    {
        $link = trim((string) $link);
        $colon = strpos($link, ':');
        if ($colon === false) {
            return ['', ''];
        }
        $kind = substr($link, 0, $colon);

        return in_array($kind, self::KINDS, true) ? [$kind, substr($link, $colon + 1)] : ['', ''];
    }

    /** Whether a stored link is one the carousel can follow (no scripts, no other schemes). */
    public static function isValid(?string $link): bool
    {
        [$kind, $value] = self::split($link);

        return match ($kind) {
            'route' => isset(self::ROUTES[$value]),
            'leader', 'page', 'post' => $value !== '' && preg_match('/^[A-Za-z0-9\-]+$/', $value) === 1,
            'url' => self::isSafeUrl($value),
            default => false,
        };
    }

    public static function isSafeUrl(string $url): bool
    {
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return preg_match('#^/[^\s<>"\']*$#', $url) === 1;
        }

        return preg_match('#^https?://#i', $url) === 1 && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /** The address in this language, or null when the target is gone or not published. */
    public static function url(?string $link, string $locale): ?string
    {
        if (! self::isValid($link)) {
            return null;
        }
        [$kind, $value] = self::split($link);

        try {
            return match ($kind) {
                'route' => Route::has($value) ? route($value, ['locale' => $locale]) : null,
                'leader' => self::record(Leader::class, $value, $locale),
                'page' => self::record(Page::class, $value, $locale),
                'post' => self::record(Post::class, $value, $locale),
                'url' => $value,
                default => null,
            };
        } catch (Throwable) {
            return null;
        }
    }

    /** Whether the address leaves this site (opened with rel="noopener"). */
    public static function isExternal(?string $link): bool
    {
        [$kind, $value] = self::split($link);

        return $kind === 'url' && ! str_starts_with($value, '/') && parse_url($value, PHP_URL_HOST) !== request()->getHost();
    }

    /**
     * @param  class-string<Model>  $class
     */
    private static function record(string $class, string $id, string $locale): ?string
    {
        $record = $class::query()->find($id);

        return $record instanceof Model ? PublicLinks::preferred($record, $locale) : null;
    }
}
