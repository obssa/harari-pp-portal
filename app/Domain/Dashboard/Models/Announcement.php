<?php

namespace App\Domain\Dashboard\Models;

use App\Domain\Content\Concerns\BelongsToSite;
use App\Domain\Content\Concerns\TracksEditors;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * An internal notice from a Super Admin to staff (PLAN.md §7 dashboard:
 * release notes, deadlines), shown on everyone's dashboard while current.
 *
 * @property int $id
 * @property string $title
 * @property ?string $body
 * @property string $tone
 * @property ?CarbonImmutable $starts_at
 * @property ?CarbonImmutable $ends_at
 */
class Announcement extends Model
{
    use BelongsToSite;
    use LogsActivity;
    use TracksEditors;

    public const TONES = ['info', 'warning', 'success'];

    protected $fillable = ['title', 'body', 'tone', 'starts_at', 'ends_at'];

    protected $attributes = ['tone' => 'info'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('dashboard')->logOnly(['title', 'body', 'tone', 'starts_at', 'ends_at'])->logOnlyDirty();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        $now = CarbonImmutable::now();

        return $query
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now));
    }
}
