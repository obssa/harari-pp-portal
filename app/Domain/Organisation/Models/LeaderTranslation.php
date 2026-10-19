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
 * One language of a leader's profile.
 *
 * @property string $leader_id
 * @property string $name
 * @property ?string $position
 * @property ?string $summary
 * @property ?array<string, mixed> $body
 * @property ContentStatus $status
 * @property ?CarbonImmutable $published_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Leader $leader
 */
class LeaderTranslation extends Translation
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
            ->logOnly(['locale', 'name', 'slug', 'position', 'summary', 'status', 'published_at'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<Leader, $this>
     */
    public function leader(): BelongsTo
    {
        return $this->belongsTo(Leader::class);
    }
}
