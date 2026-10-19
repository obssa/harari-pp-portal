<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Concerns\BelongsToSite;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * An old address that sends visitors to its new one (PLAN.md §11: 301
 * redirects on slug change). Paths are site-relative, e.g. /en/news/old.
 *
 * @property int $id
 * @property string $from_path
 * @property string $to_path
 * @property int $status
 * @property int $hits
 * @property bool $automatic
 */
class Redirect extends Model
{
    use BelongsToSite;
    use LogsActivity;

    protected $fillable = ['from_path', 'to_path', 'status', 'automatic', 'created_by'];

    protected $attributes = ['status' => 301, 'automatic' => false, 'hits' => 0];

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'hits' => 'integer',
            'automatic' => 'boolean',
            'last_hit_at' => 'immutable_datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('content')->logOnly(['from_path', 'to_path', 'status'])->logOnlyDirty();
    }

    public static function normalise(string $path): string
    {
        $path = '/'.ltrim((string) parse_url($path, PHP_URL_PATH), '/');

        return $path === '/' ? $path : rtrim($path, '/');
    }

    /**
     * Records that `$from` moved to `$to`; earlier redirects to `$from` are
     * pointed straight at `$to` (no chains), and a redirect from the new
     * address is removed (no loops).
     */
    public static function moved(string $from, string $to): void
    {
        $from = self::normalise($from);
        $to = self::normalise($to);
        if ($from === $to) {
            return;
        }

        self::query()->where('from_path', $to)->delete();
        self::query()->where('to_path', $from)->update(['to_path' => $to]);
        self::query()->updateOrCreate(['from_path' => $from], ['to_path' => $to, 'status' => 301, 'automatic' => true]);
    }
}
