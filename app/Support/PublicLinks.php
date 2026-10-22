<?php

namespace App\Support;

use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\Post;
use App\Domain\Documents\Models\Document;
use App\Domain\Events\Models\Event;
use App\Domain\Forms\Models\Form;
use App\Domain\Media\Models\Gallery;
use App\Domain\Media\Models\Video;
use App\Domain\Organisation\Models\Leader;
use App\Domain\Organisation\Models\League;
use App\Domain\Organisation\Models\Office;
use App\Domain\Projects\Models\Project;
use App\Domain\Translation\Support\Locales;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Where a record can be read on the public site (ADR-0068): its address in
 * each language that is live there now. Drafts, scheduled languages,
 * inactive forms and members-only documents have none.
 */
final class PublicLinks
{
    /** Public route of each kind of record, keyed by the record's class. */
    private const ROUTES = [
        Post::class => 'news.show',
        Event::class => 'events.show',
        Document::class => 'documents.show',
        Project::class => 'projects.show',
        Gallery::class => 'gallery.show',
        Video::class => 'videos.show',
        Leader::class => 'leaders.show',
        Office::class => 'offices.show',
        League::class => 'leagues.show',
        Form::class => 'forms.show',
    ];

    /**
     * @return array<string, string> language => address, in the site's language order
     */
    public static function for(Model $record): array
    {
        try {
            return self::resolve($record);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * The address in the given language, else the first live one.
     */
    public static function preferred(Model $record, ?string $locale = null): ?string
    {
        $links = self::for($record);

        return $links[$locale ?? app()->getLocale()] ?? ($links === [] ? null : reset($links));
    }

    /**
     * @return array<string, string>
     */
    private static function resolve(Model $record): array
    {
        if (($record instanceof Form && ! $record->active) || ($record instanceof Document && $record->access === Document::ACCESS_MEMBERS)) {
            return [];
        }

        $route = self::ROUTES[$record::class] ?? null;
        if ($route === null && ! $record instanceof Page) {
            return [];
        }

        $record->loadMissing('translations');
        $links = [];
        foreach (Locales::all() as $locale) {
            $translation = $record->getRelationValue('translations')?->firstWhere('locale', $locale);
            if (! $translation instanceof Model || ! method_exists($translation, 'isLive') || ! $translation->isLive()) {
                continue;
            }

            if ($record instanceof Page) {
                $path = $record->path($locale);
                if ($path !== null) {
                    $links[$locale] = route('page.show', ['locale' => $locale, 'path' => $path]);
                }
            } elseif (filled($translation->getAttribute('slug'))) {
                $links[$locale] = route((string) $route, ['locale' => $locale, 'slug' => $translation->getAttribute('slug')]);
            }
        }

        return $links;
    }
}
