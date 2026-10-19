<?php

namespace App\Domain\Organisation\Models;

use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\Translation;
use App\Domain\Organisation\Concerns\PublishedPerLanguage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One language of an office.
 *
 * @property string $office_id
 * @property string $name
 * @property ?string $summary
 * @property ?array<string, mixed> $body
 * @property ?string $address
 * @property ?string $hours
 * @property ContentStatus $status
 * @property ?CarbonImmutable $published_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Office $office
 */
class OfficeTranslation extends Translation
{
    use LogsActivity;
    use PublishedPerLanguage;

    protected string $sluggable = 'name';

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return [
            'body' => 'array',
            'status' => ContentStatus::class,
            'published_at' => 'immutable_datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('organisation')
            ->logOnly(['locale', 'name', 'slug', 'summary', 'address', 'hours', 'status', 'published_at'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<Office, $this>
     */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }
}
