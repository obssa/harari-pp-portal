<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $tag_id
 * @property string $name
 */
class TagTranslation extends Translation
{
    use LogsActivity;

    protected string $sluggable = 'name';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('content')->logOnly(['locale', 'name', 'slug'])->logOnlyDirty();
    }

    /**
     * @return BelongsTo<Tag, $this>
     */
    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }
}
