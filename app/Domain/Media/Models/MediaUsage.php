<?php

namespace App\Domain\Media\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One place a library file is used, e.g. a post's featured image.
 *
 * @property int $id
 * @property int $media_id
 * @property string $usable_type
 * @property int $usable_id
 * @property string $context
 */
class MediaUsage extends Model
{
    protected $fillable = ['media_id', 'usable_type', 'usable_id', 'context'];

    /**
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function usable(): MorphTo
    {
        return $this->morphTo();
    }

    /** "Post #12 (featured image)" for the admin. */
    public function label(): string
    {
        return __('admin.media.usage.label', [
            'type' => class_basename($this->usable_type),
            'id' => $this->usable_id,
            'context' => $this->context,
        ]);
    }
}
