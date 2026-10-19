<?php

namespace App\Domain\Sites\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A portal site. Only the default site exists for now; sub-sites for offices
 * or leagues can be added later without schema changes (PLAN.md §7).
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property ?string $domain
 * @property bool $is_default
 */
class Site extends Model
{
    protected $fillable = ['key', 'name', 'domain', 'is_default'];

    private static ?Site $current = null;

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    /**
     * The site serving this request: the one whose domain matches the host,
     * otherwise the default site.
     */
    public static function current(): self
    {
        if (self::$current !== null) {
            return self::$current;
        }

        $host = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request()->getHost();

        $byDomain = $host ? self::query()->where('domain', $host)->first() : null;

        return self::$current = $byDomain ?? self::query()->where('is_default', true)->firstOrFail();
    }

    /**
     * Runs the rest of a job or command as this site (settings, content).
     */
    public static function setCurrent(self $site): void
    {
        self::$current = $site;
    }

    public static function forgetCurrent(): void
    {
        self::$current = null;
    }
}
