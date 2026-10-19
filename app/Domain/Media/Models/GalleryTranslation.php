<?php

namespace App\Domain\Media\Models;

use App\Domain\Content\Concerns\PublishedPerLanguage;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\Translation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One language of a photo album.
 *
 * @property string $gallery_id
 * @property string $title
 * @property ?string $summary
 * @property ?array<string, mixed> $body
 * @property ContentStatus $status
 * @property ?CarbonImmutable $published_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Gallery $gallery
 */
class GalleryTranslation extends Translation
{
    use LogsActivity;
    use PublishedPerLanguage;

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
        return LogOptions::defaults()->useLogName('media')
            ->logOnly(['locale', 'title', 'slug', 'summary', 'status', 'published_at'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<Gallery, $this>
     */
    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }
}
