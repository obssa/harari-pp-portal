<?php

namespace App\Domain\Events\Models;

use App\Domain\Content\Concerns\BelongsToSite;
use App\Domain\Content\Concerns\HasTranslations;
use App\Domain\Content\Concerns\TracksEditors;
use App\Domain\Content\Contracts\TranslatableModel;
use App\Domain\Media\Models\Media;
use App\Domain\Organisation\Models\Leader;
use App\Domain\Organisation\Models\League;
use App\Domain\Organisation\Models\Office;
use App\Services\Calendar\EthiopianCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * An event (PLAN.md §4 Get involved, ADR-0025): when and where (a venue
 * with a map pin, or online), the agenda and speakers per language, and
 * whether people can register.
 *
 * @property string $id
 * @property CarbonImmutable $starts_at
 * @property ?CarbonImmutable $ends_at
 * @property bool $all_day
 * @property ?float $latitude
 * @property ?float $longitude
 * @property ?string $online_url
 * @property ?int $cover_id
 * @property ?string $office_id
 * @property ?string $league_id
 * @property bool $registration_enabled
 * @property ?int $capacity
 * @property bool $waitlist
 * @property ?CarbonImmutable $registration_closes_at
 * @property-read ?Media $cover
 * @property-read ?Office $office
 * @property-read ?League $league
 */
class Event extends Model implements TranslatableModel
{
    use BelongsToSite;

    /** @use HasTranslations<EventTranslation> */
    use HasTranslations;

    use HasUlids;
    use LogsActivity;
    use SoftDeletes;
    use TracksEditors;

    protected $fillable = [
        'starts_at', 'ends_at', 'all_day', 'latitude', 'longitude', 'online_url', 'cover_id', 'office_id', 'league_id',
        'registration_enabled', 'capacity', 'waitlist', 'registration_closes_at',
    ];

    protected $attributes = ['all_day' => false, 'registration_enabled' => false, 'waitlist' => true];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'all_day' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'registration_enabled' => 'boolean',
            'capacity' => 'integer',
            'waitlist' => 'boolean',
            'registration_closes_at' => 'immutable_datetime',
        ];
    }

    public static function translationModel(): string
    {
        return EventTranslation::class;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('events')
            ->logOnly($this->fillable)
            ->logOnlyDirty()->dontSubmitEmptyLogs();
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

    /**
     * @return BelongsToMany<Leader, $this>
     */
    public function speakers(): BelongsToMany
    {
        return $this->belongsToMany(Leader::class)->withPivot('sort')->orderByPivot('sort');
    }

    /** When the event is over (its end, or the end of its first day in Addis Ababa). */
    public function endsAt(): CarbonImmutable
    {
        return $this->ends_at ?? $this->starts_at->setTimezone(EthiopianCalendar::timezone())->endOfDay();
    }

    /** The first day of the event in Addis Ababa, for all-day dates. */
    public function startDay(): CarbonImmutable
    {
        return $this->starts_at->setTimezone(EthiopianCalendar::timezone())->startOfDay();
    }

    /** The day after the last day, as calendars expect for all-day events. */
    public function dayAfter(): CarbonImmutable
    {
        return ($this->ends_at ?? $this->starts_at)->setTimezone(EthiopianCalendar::timezone())->startOfDay()->addDay();
    }

    public function isPast(): bool
    {
        return $this->endsAt()->isPast();
    }

    public function hasMap(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * Events that have not ended yet.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('ends_at', '>=', CarbonImmutable::now('UTC'))
            ->orWhere(fn (Builder $open) => $open->whereNull('ends_at')->where('starts_at', '>=', self::today())));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePast(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('ends_at', '<', CarbonImmutable::now('UTC'))
            ->orWhere(fn (Builder $open) => $open->whereNull('ends_at')->where('starts_at', '<', self::today())));
    }

    /** The start of today in Addis Ababa, in UTC as stored. */
    private static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(EthiopianCalendar::timezone())->startOfDay()->utc();
    }

    /** The name for the admin: this language, its fallbacks, or any language. */
    public function title(?string $locale = null): string
    {
        return (string) $this->translationOrAny($locale)?->getAttribute('title');
    }
}
