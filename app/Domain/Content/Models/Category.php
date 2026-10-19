<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Concerns\BelongsToSite;
use App\Domain\Content\Concerns\HasTranslations;
use App\Domain\Content\Concerns\TracksEditors;
use App\Domain\Content\Contracts\TranslatableModel;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A category of content (PLAN.md §7, §10), named per language, optionally
 * nested. `type` keeps post categories apart from later content types.
 *
 * @property string $id
 * @property string $type
 * @property ?string $parent_id
 * @property int $sort
 */
class Category extends Model implements TranslatableModel
{
    use BelongsToSite;

    /** @use HasTranslations<CategoryTranslation> */
    use HasTranslations;

    use HasUlids;
    use LogsActivity;
    use SoftDeletes;
    use TracksEditors;

    public const TYPE_POST = 'post';

    protected $fillable = ['type', 'parent_id', 'sort'];

    protected $attributes = ['type' => self::TYPE_POST, 'sort' => 0];

    public static function translationModel(): string
    {
        return CategoryTranslation::class;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('content')->logOnly(['type', 'parent_id', 'sort'])->logOnlyDirty();
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function name(?string $locale = null): string
    {
        return (string) $this->translation($locale)?->name;
    }
}
