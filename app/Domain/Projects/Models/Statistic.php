<?php

namespace App\Domain\Projects\Models;

use App\Domain\Content\Concerns\BelongsToSite;
use App\Domain\Content\Concerns\HasTranslations;
use App\Domain\Content\Concerns\TracksEditors;
use App\Domain\Content\Contracts\TranslatableModel;
use App\Domain\Content\Models\Category;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A number with its label, unit, source and as-of date (PLAN.md §7:
 * statistics blocks reusable in pages and counters, ADR-0027). It belongs
 * to a sector for the achievements page, to a project, or to both.
 *
 * @property int $id
 * @property ?string $sector_id
 * @property ?string $project_id
 * @property string $value
 * @property ?CarbonImmutable $as_of
 * @property ?string $source_url
 * @property bool $on_achievements
 * @property int $sort
 * @property-read ?Category $sector
 * @property-read ?Project $project
 */
class Statistic extends Model implements TranslatableModel
{
    use BelongsToSite;

    /** @use HasTranslations<StatisticTranslation> */
    use HasTranslations;

    use LogsActivity;
    use TracksEditors;

    protected $fillable = ['sector_id', 'project_id', 'value', 'as_of', 'source_url', 'on_achievements', 'sort'];

    protected $attributes = ['on_achievements' => true, 'sort' => 0];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'as_of' => 'immutable_date',
            'on_achievements' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public static function translationModel(): string
    {
        return StatisticTranslation::class;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('projects')
            ->logOnly($this->fillable)
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'sector_id');
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** The number as readers see it: no ".00", grouped thousands. */
    public function formatted(): string
    {
        $value = (float) $this->value;
        $decimals = fmod($value, 1.0) === 0.0 ? 0 : (fmod($value * 10, 1.0) === 0.0 ? 1 : 2);

        return (string) Number::format($value, precision: $decimals, locale: 'en');
    }

    /** The label for the admin: this language, its fallbacks, or any language. */
    public function label(?string $locale = null): string
    {
        return (string) $this->translationOrAny($locale)?->getAttribute('label');
    }
}
