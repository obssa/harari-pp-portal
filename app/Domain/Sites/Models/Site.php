<?php

namespace App\Domain\Sites\Models;

use App\Domain\Media\Models\Media;
use App\Domain\Media\Support\ImageConversions;
use App\Domain\Sites\Support\CurrentSite;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;

/**
 * A portal site. Only the default site exists for now; sub-sites for offices
 * or leagues can be added later without schema changes (PLAN.md §7).
 * The site owns its media library (ADR-0009).
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property ?string $domain
 * @property bool $is_default
 */
class Site extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = ['key', 'name', 'domain', 'is_default'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(Media::LIBRARY);
    }

    public function registerMediaConversions(?BaseMedia $media = null): void
    {
        ImageConversions::register($this, $media);
    }

    /**
     * The site serving this request: the one whose domain matches the host,
     * otherwise the default site. Kept for the request or job only (ADR-0061).
     */
    public static function current(): self
    {
        $holder = app(CurrentSite::class);
        if ($holder->site !== null) {
            return $holder->site;
        }

        $host = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request()->getHost();

        $byDomain = $host ? self::query()->where('domain', $host)->first() : null;

        return $holder->site = $byDomain ?? self::query()->where('is_default', true)->firstOrFail();
    }

    /**
     * Runs the rest of a job or command as this site (settings, content).
     */
    public static function setCurrent(self $site): void
    {
        app(CurrentSite::class)->site = $site;
    }

    public static function forgetCurrent(): void
    {
        app(CurrentSite::class)->site = null;
    }
}
