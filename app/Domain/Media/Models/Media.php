<?php

namespace App\Domain\Media\Models;

use App\Domain\Media\Actions\RegenerateConversions;
use App\Domain\Media\Enums\MediaKind;
use App\Domain\Sites\Models\Site;
use App\Domain\Translation\Support\Locales;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;

/**
 * A file in the media library (PLAN.md §7, §10): alt text per language,
 * credit, focal point, folder, tags. Library files belong to the site.
 *
 * @property ?int $site_id
 * @property ?int $folder_id
 * @property ?int $uploaded_by
 * @property MediaKind $kind
 * @property ?array<string, string> $alt
 * @property ?string $credit
 * @property int $focal_x
 * @property int $focal_y
 * @property ?int $width
 * @property ?int $height
 * @property ?list<string> $tags
 * @property-read ?MediaFolder $folder
 * @property-read ?User $uploader
 */
class Media extends BaseMedia
{
    use LogsActivity;

    public const LIBRARY = 'library';

    protected function casts(): array
    {
        return [
            'kind' => MediaKind::class,
            'alt' => 'array',
            'tags' => 'array',
            'focal_x' => 'integer',
            'focal_y' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Media $media): void {
            $media->kind ??= MediaKind::fromMime($media->mime_type) ?? MediaKind::Document;
        });

        // A new focal point means new crops.
        static::updated(function (Media $media): void {
            if ($media->wasChanged(['focal_x', 'focal_y'])) {
                app(RegenerateConversions::class)->handle($media);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('media')
            ->logOnly(['name', 'file_name', 'folder_id', 'alt', 'credit', 'focal_x', 'focal_y', 'tags'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<MediaFolder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return HasMany<MediaUsage, $this>
     */
    public function usages(): HasMany
    {
        return $this->hasMany(MediaUsage::class, 'media_id');
    }

    /**
     * Files of the current site's library.
     *
     * @param  Builder<Media>  $query
     * @return Builder<Media>
     */
    public function scopeLibrary(Builder $query): Builder
    {
        return $query->where('collection_name', self::LIBRARY)->where('site_id', Site::current()->id);
    }

    /**
     * Images still missing alt text in any of the given languages (Harari is
     * written by hand later and falls back to Amharic meanwhile).
     *
     * @param  Builder<Media>  $query
     * @param  list<string>  $locales
     * @return Builder<Media>
     */
    public function scopeMissingAlt(Builder $query, array $locales = ['en', 'am', 'om']): Builder
    {
        return $query->whereIn('kind', [MediaKind::Image->value, MediaKind::Vector->value])
            ->where(function (Builder $query) use ($locales): void {
                $query->whereNull('alt');
                foreach ($locales as $locale) {
                    $query->orWhereNull("alt->{$locale}")->orWhere("alt->{$locale}", '');
                }
            });
    }

    /**
     * @param  Builder<Media>  $query
     * @return Builder<Media>
     */
    public function scopeUnused(Builder $query): Builder
    {
        return $query->whereDoesntHave('usages');
    }

    /**
     * Alt text in this language, following the fallback chain (har → am → en).
     */
    public function altFor(?string $locale = null): string
    {
        foreach (Locales::fallbackChain($locale ?? app()->getLocale()) as $candidate) {
            if (filled($this->alt[$candidate] ?? null)) {
                return (string) $this->alt[$candidate];
            }
        }

        return '';
    }

    /** Alt text written for exactly this language (no fallback). */
    public function hasAltFor(string $locale): bool
    {
        return filled($this->alt[$locale] ?? null);
    }

    public function isUsed(): bool
    {
        return $this->usages()->exists();
    }

    public function isImage(): bool
    {
        return $this->kind === MediaKind::Image || $this->kind === MediaKind::Vector;
    }

    /**
     * The admin preview: the small copy for photos, the file itself for SVG.
     */
    public function adminPreviewUrl(): ?string
    {
        return match ($this->kind) {
            MediaKind::Image => $this->hasGeneratedConversion('thumb') ? $this->getUrl('thumb') : null,
            MediaKind::Vector => $this->getUrl(),
            default => null,
        };
    }
}
