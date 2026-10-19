<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $category_id
 * @property string $name
 * @property ?string $description
 */
class CategoryTranslation extends Translation
{
    use LogsActivity;

    protected string $sluggable = 'name';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('content')->logOnly(['locale', 'name', 'slug', 'description'])->logOnlyDirty();
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
