<?php

namespace App\Domain\Import\Models;

use App\Domain\Content\Concerns\BelongsToSite;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A public channel content is imported from (docs/content/sources.md):
 * priority 1 is the branch's own, 2 the communication office's. The cursor
 * is the newest message already read.
 *
 * @property int $id
 * @property string $platform
 * @property string $handle
 * @property string $name
 * @property int $priority
 * @property string $default_locale
 * @property bool $enabled
 * @property ?int $cursor
 * @property ?CarbonImmutable $last_fetched_at
 * @property ?string $last_error
 */
class ContentSource extends Model
{
    use BelongsToSite;
    use LogsActivity;

    public const HANDLE_PATTERN = '/^[A-Za-z0-9_]{5,32}$/';

    protected $fillable = ['platform', 'handle', 'name', 'priority', 'default_locale', 'enabled', 'cursor', 'last_fetched_at', 'last_error'];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'enabled' => 'boolean',
            'cursor' => 'integer',
            'last_fetched_at' => 'immutable_datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('import')
            ->logOnly(['handle', 'name', 'priority', 'default_locale', 'enabled'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    /**
     * @return HasMany<ContentImport, $this>
     */
    public function imports(): HasMany
    {
        return $this->hasMany(ContentImport::class);
    }

    public function url(): string
    {
        return 'https://t.me/'.$this->handle;
    }
}
