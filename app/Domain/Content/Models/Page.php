<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Concerns\BelongsToSite;
use App\Domain\Content\Concerns\HasTranslations;
use App\Domain\Content\Concerns\TracksEditors;
use App\Domain\Content\Contracts\TranslatableModel;
use App\Domain\Media\Models\Media;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A page such as About, History or Vision (PLAN.md §3, §7): nested, with a
 * template and a body per language. Its address is the chain of slugs of
 * its ancestors in that language, e.g. /en/about/history.
 *
 * @property string $id
 * @property string $template
 * @property ?string $parent_id
 * @property int $sort
 * @property ?int $featured_image_id
 * @property-read ?Page $parent
 * @property-read ?Media $featuredImage
 */
class Page extends Model implements TranslatableModel
{
    use BelongsToSite;

    /** @use HasTranslations<PageTranslation> */
    use HasTranslations;

    use HasUlids;
    use LogsActivity;
    use SoftDeletes;
    use TracksEditors;

    public const TEMPLATES = ['default', 'wide', 'landing'];

    /** Deepest nesting, so addresses stay short. */
    public const MAX_DEPTH = 3;

    /**
     * First segments of the fixed public sections (PLAN.md §3). Page routes
     * come last, so a page with one of these addresses could never be
     * reached; such a page gets "-page" added instead.
     */
    public const RESERVED_SLUGS = [
        'news', 'offices', 'leaders', 'leagues', 'events', 'documents', 'projects', 'social', 'search',
        'gallery', 'videos', 'live', 'contact', 'join', 'members', 'volunteer', 'newsletter', 'feedback',
        'preview', 'feed',
    ];

    protected $fillable = ['template', 'parent_id', 'sort', 'featured_image_id'];

    protected $attributes = ['template' => 'default', 'sort' => 0];

    public static function translationModel(): string
    {
        return PageTranslation::class;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('content')
            ->logOnly(['template', 'parent_id', 'sort', 'featured_image_id'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Page, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_image_id');
    }

    /**
     * @return MorphMany<Revision, $this>
     */
    public function revisions(): MorphMany
    {
        return $this->morphMany(Revision::class, 'revisionable');
    }

    /**
     * This page and its ancestors, root first.
     *
     * @return list<Page>
     */
    public function lineage(): array
    {
        $chain = [$this];
        $seen = [$this->id];
        $parentId = $this->parent_id;
        while ($parentId !== null && ! in_array($parentId, $seen, true) && count($chain) <= self::MAX_DEPTH) {
            $parent = self::query()->with('translations')->find($parentId);
            if ($parent === null) {
                break;
            }
            array_unshift($chain, $parent);
            $seen[] = $parent->id;
            $parentId = $parent->parent_id;
        }

        return $chain;
    }

    /**
     * The address of this page in a language ("about/history"), or null when
     * it or one of its ancestors has no translation there.
     */
    public function path(string $locale): ?string
    {
        $slugs = [];
        foreach ($this->lineage() as $page) {
            $translation = $page->translation($locale, fallback: false);
            if ($translation === null || $translation->slug === null) {
                return null;
            }
            $slugs[] = $translation->slug;
        }

        return implode('/', $slugs);
    }

    public function title(?string $locale = null): string
    {
        return (string) $this->translation($locale)?->title;
    }
}
