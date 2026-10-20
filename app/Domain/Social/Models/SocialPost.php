<?php

namespace App\Domain\Social\Models;

use App\Domain\Media\Models\Media;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One public post read from a source (ADR-0031): its text as published,
 * the link to the original, a copy of its first picture in the library,
 * and whether staff hid or pinned it.
 *
 * @property string $id
 * @property string $social_source_id
 * @property string $external_id
 * @property CarbonImmutable $posted_at
 * @property ?string $text
 * @property string $permalink
 * @property ?int $image_id
 * @property bool $has_video
 * @property ?array<string, int> $metrics
 * @property bool $is_hidden
 * @property bool $is_pinned
 * @property-read SocialSource $source
 * @property-read ?Media $image
 */
class SocialPost extends Model
{
    use HasUlids;
    use LogsActivity;

    protected $fillable = ['external_id', 'posted_at', 'text', 'permalink', 'image_id', 'has_video', 'metrics', 'is_hidden', 'is_pinned'];

    protected $attributes = ['has_video' => false, 'is_hidden' => false, 'is_pinned' => false];

    protected function casts(): array
    {
        return [
            'posted_at' => 'immutable_datetime',
            'has_video' => 'boolean',
            'metrics' => 'array',
            'is_hidden' => 'boolean',
            'is_pinned' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('social')
            ->logOnly(['is_hidden', 'is_pinned'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<SocialSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(SocialSource::class, 'social_source_id');
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_id');
    }

    /**
     * What the public hub may show: not hidden, from an enabled source.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_hidden', false)->whereHas('source', fn (Builder $source) => $source->where('enabled', true));
    }
}
