<?php

namespace App\Domain\Organisation\Models;

use App\Domain\Content\Concerns\PublishedPerLanguage;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\Translation;
use App\Domain\Content\Support\BodyRenderer;
use App\Domain\Search\Concerns\SearchableTranslation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One language of a league or forum.
 *
 * @property string $league_id
 * @property string $name
 * @property ?string $summary
 * @property ?array<string, mixed> $body
 * @property ContentStatus $status
 * @property ?CarbonImmutable $published_at
 * @property ?CarbonImmutable $updated_at
 * @property-read League $league
 */
class LeagueTranslation extends Translation
{
    use LogsActivity;
    use PublishedPerLanguage;
    use SearchableTranslation;

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

    /**
     * @return list<?string>
     */
    public function searchableParts(): array
    {
        return [$this->name, $this->summary, BodyRenderer::toText($this->body)];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('organisation')
            ->logOnly(['locale', 'name', 'slug', 'summary', 'status', 'published_at'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<League, $this>
     */
    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }
}
