<?php

namespace App\Domain\Media\Models;

use App\Domain\Content\Concerns\BelongsToSite;
use App\Domain\Content\Concerns\HasTranslations;
use App\Domain\Content\Concerns\TracksEditors;
use App\Domain\Content\Contracts\TranslatableModel;
use App\Domain\Organisation\Models\League;
use App\Domain\Organisation\Models\Office;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A photo album (PLAN.md §4 News & Media, ADR-0023): library photos in
 * order with captions per language, a cover, the day the photos were taken
 * and the office or league it belongs to.
 *
 * @property string $id
 * @property ?int $cover_id
 * @property ?string $office_id
 * @property ?string $league_id
 * @property ?CarbonImmutable $taken_on
 * @property bool $allow_download
 * @property-read ?Media $cover
 * @property-read ?Office $office
 * @property-read ?League $league
 */
class Gallery extends Model implements TranslatableModel
{
    use BelongsToSite;

    /** @use HasTranslations<GalleryTranslation> */
    use HasTranslations;

    use HasUlids;
    use LogsActivity;
    use SoftDeletes;
    use TracksEditors;

    /** Most photos in one album. */
    public const MAX_ITEMS = 200;

    protected $fillable = ['cover_id', 'office_id', 'league_id', 'taken_on', 'allow_download'];

    protected $attributes = ['allow_download' => false];

    protected function casts(): array
    {
        return [
            'taken_on' => 'immutable_date',
            'allow_download' => 'boolean',
        ];
    }

    public static function translationModel(): string
    {
        return GalleryTranslation::class;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('media')
            ->logOnly(['cover_id', 'office_id', 'league_id', 'taken_on', 'allow_download'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    /**
     * @return HasMany<GalleryItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(GalleryItem::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_id');
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

    /** The name for the admin: this language, its fallbacks, or any language. */
    public function title(?string $locale = null): string
    {
        return (string) $this->translationOrAny($locale)?->getAttribute('title');
    }
}
