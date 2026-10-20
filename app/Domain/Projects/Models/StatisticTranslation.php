<?php

namespace App\Domain\Projects\Models;

use App\Domain\Content\Models\Translation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * The label, unit and source of a statistic in one language. Statistics
 * are shown with whatever shows them, so they have no publish switch.
 *
 * @property int $statistic_id
 * @property string $label
 * @property ?string $unit
 * @property ?string $source
 * @property-read Statistic $statistic
 */
class StatisticTranslation extends Translation
{
    use LogsActivity;

    protected string $sluggable = 'label';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('projects')
            ->logOnly(['locale', 'label', 'unit', 'source'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<Statistic, $this>
     */
    public function statistic(): BelongsTo
    {
        return $this->belongsTo(Statistic::class);
    }
}
