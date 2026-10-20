<?php

namespace App\Domain\Social\Models;

use App\Domain\Content\Concerns\BelongsToSite;
use App\Domain\Content\Concerns\TracksEditors;
use App\Domain\Organisation\Models\League;
use App\Domain\Organisation\Models\Office;
use App\Domain\Social\Enums\Platform;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * An account of the branch, an office or a league on one network
 * (ADR-0031). Credentials, when a network needs them, are encrypted and
 * never logged.
 *
 * @property string $id
 * @property Platform $platform
 * @property string $handle
 * @property string $name
 * @property ?string $office_id
 * @property ?string $league_id
 * @property ?array<string, mixed> $credentials
 * @property bool $enabled
 * @property int $interval_minutes
 * @property ?CarbonImmutable $last_fetched_at
 * @property ?string $last_error
 * @property-read ?Office $office
 * @property-read ?League $league
 */
class SocialSource extends Model
{
    use BelongsToSite;
    use HasUlids;
    use LogsActivity;
    use TracksEditors;

    protected $fillable = ['platform', 'handle', 'name', 'office_id', 'league_id', 'credentials', 'enabled', 'interval_minutes'];

    protected $hidden = ['credentials'];

    protected $attributes = ['enabled' => true, 'interval_minutes' => 60];

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'credentials' => 'encrypted:array',
            'enabled' => 'boolean',
            'interval_minutes' => 'integer',
            'last_fetched_at' => 'immutable_datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('social')
            ->logOnly(['platform', 'handle', 'name', 'office_id', 'league_id', 'enabled', 'interval_minutes'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    /**
     * @return HasMany<SocialPost, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(SocialPost::class);
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

    public function url(): string
    {
        return $this->platform->profileUrl($this->handle);
    }

    /**
     * Sources whose next fetch is due.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query->where('enabled', true)->where(fn (Builder $q) => $q->whereNull('last_fetched_at')
            ->orWhereRaw('TIMESTAMPDIFF(MINUTE, last_fetched_at, ?) >= interval_minutes', [CarbonImmutable::now('UTC')->toDateTimeString()]));
    }
}
