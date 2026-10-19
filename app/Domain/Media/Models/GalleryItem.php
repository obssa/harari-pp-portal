<?php

namespace App\Domain\Media\Models;

use App\Domain\Translation\Support\Locales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One photo in an album, with its caption per language.
 *
 * @property int $id
 * @property string $gallery_id
 * @property int $media_id
 * @property int $sort
 * @property ?array<string, string> $caption
 * @property-read Media $media
 */
class GalleryItem extends Model
{
    protected $fillable = ['media_id', 'sort', 'caption'];

    protected function casts(): array
    {
        return [
            'sort' => 'integer',
            'caption' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /** The caption in this language or the nearest one in the fallback chain. */
    public function captionFor(?string $locale = null): string
    {
        foreach (Locales::fallbackChain($locale ?? app()->getLocale()) as $candidate) {
            if (filled($this->caption[$candidate] ?? null)) {
                return (string) $this->caption[$candidate];
            }
        }

        return '';
    }
}
