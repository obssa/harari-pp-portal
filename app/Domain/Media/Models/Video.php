<?php

namespace App\Domain\Media\Models;

use App\Domain\Content\Concerns\BelongsToSite;
use App\Domain\Content\Concerns\HasTranslations;
use App\Domain\Content\Concerns\TracksEditors;
use App\Domain\Content\Contracts\TranslatableModel;
use App\Domain\Content\Models\Category;
use App\Domain\Media\Support\VideoLink;
use App\Domain\Organisation\Models\League;
use App\Domain\Organisation\Models\Office;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A video or live broadcast (PLAN.md §4 News & Media, ADR-0024): a YouTube
 * video played with the privacy-enhanced player, or a Facebook or Telegram
 * video linked from its thumbnail, with a title per language.
 *
 * @property string $id
 * @property string $provider
 * @property string $url
 * @property ?string $youtube_id
 * @property ?int $thumbnail_id
 * @property ?string $category_id
 * @property ?string $office_id
 * @property ?string $league_id
 * @property ?CarbonImmutable $recorded_on
 * @property bool $is_featured
 * @property bool $is_live
 * @property ?CarbonImmutable $live_starts_at
 * @property ?CarbonImmutable $live_ends_at
 * @property-read ?Media $thumbnail
 * @property-read ?Category $category
 * @property-read ?Office $office
 * @property-read ?League $league
 */
class Video extends Model implements TranslatableModel
{
    use BelongsToSite;

    /** @use HasTranslations<VideoTranslation> */
    use HasTranslations;

    use HasUlids;
    use LogsActivity;
    use SoftDeletes;
    use TracksEditors;

    /** A live broadcast without an end time counts as live for this long. */
    public const LIVE_HOURS = 6;

    protected $fillable = ['provider', 'url', 'youtube_id', 'thumbnail_id', 'category_id', 'office_id', 'league_id', 'recorded_on', 'is_featured', 'is_live', 'live_starts_at', 'live_ends_at'];

    protected $attributes = ['is_featured' => false, 'is_live' => false];

    protected function casts(): array
    {
        return [
            'recorded_on' => 'immutable_date',
            'is_featured' => 'boolean',
            'is_live' => 'boolean',
            'live_starts_at' => 'immutable_datetime',
            'live_ends_at' => 'immutable_datetime',
        ];
    }

    public static function translationModel(): string
    {
        return VideoTranslation::class;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('media')
            ->logOnly(['provider', 'url', 'thumbnail_id', 'category_id', 'office_id', 'league_id', 'recorded_on', 'is_featured', 'is_live', 'live_starts_at', 'live_ends_at'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function thumbnail(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'thumbnail_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Office, $this>
     */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    /**
     * @return BelongsTo<League, $this>
     */
    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function link(): ?VideoLink
    {
        return VideoLink::parse($this->url);
    }

    /** Whether a live broadcast is on now. */
    public function isLiveNow(): bool
    {
        if (! $this->is_live || $this->live_starts_at === null || $this->live_starts_at->isFuture()) {
            return false;
        }

        return $this->live_ends_at !== null
            ? $this->live_ends_at->isFuture()
            : $this->live_starts_at->greaterThan(CarbonImmutable::now()->subHours(self::LIVE_HOURS));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeLiveNow(Builder $query): Builder
    {
        $now = CarbonImmutable::now();

        return $query->where('is_live', true)
            ->where('live_starts_at', '<=', $now)
            ->where(fn (Builder $q) => $q
                ->where('live_ends_at', '>', $now)
                ->orWhere(fn (Builder $open) => $open->whereNull('live_ends_at')->where('live_starts_at', '>', $now->subHours(self::LIVE_HOURS))));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeLiveSoon(Builder $query): Builder
    {
        return $query->where('is_live', true)->where('live_starts_at', '>', CarbonImmutable::now());
    }

    /** The name for the admin: this language, its fallbacks, or any language. */
    public function title(?string $locale = null): string
    {
        return (string) $this->translationOrAny($locale)?->getAttribute('title');
    }
}
