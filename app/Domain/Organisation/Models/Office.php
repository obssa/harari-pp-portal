<?php

namespace App\Domain\Organisation\Models;

use App\Domain\Content\Concerns\BelongsToSite;
use App\Domain\Content\Concerns\HasTranslations;
use App\Domain\Content\Concerns\TracksEditors;
use App\Domain\Content\Contracts\TranslatableModel;
use App\Domain\Content\Models\Post;
use App\Domain\Media\Models\Media;
use App\Domain\Organisation\Enums\OfficeType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * An office of the party structure (PLAN.md §3, §10): the branch office,
 * woreda and kebele offices, sector offices and basic organisations, as a
 * tree with a head, contacts and a map pin.
 *
 * @property string $id
 * @property OfficeType $type
 * @property ?string $parent_id
 * @property ?string $head_leader_id
 * @property ?string $phone
 * @property ?string $email
 * @property ?float $latitude
 * @property ?float $longitude
 * @property ?int $cover_image_id
 * @property int $sort
 * @property-read ?Office $parent
 * @property-read ?Leader $head
 * @property-read ?Media $coverImage
 */
class Office extends Model implements TranslatableModel
{
    use BelongsToSite;

    /** @use HasTranslations<OfficeTranslation> */
    use HasTranslations;

    use HasUlids;
    use LogsActivity;
    use SoftDeletes;
    use TracksEditors;

    /** Branch → woreda → kebele → basic organisation is the deepest chain. */
    public const MAX_DEPTH = 4;

    protected $fillable = ['type', 'parent_id', 'head_leader_id', 'phone', 'email', 'latitude', 'longitude', 'cover_image_id', 'sort'];

    protected $attributes = ['sort' => 0];

    protected function casts(): array
    {
        return [
            'type' => OfficeType::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'sort' => 'integer',
        ];
    }

    public static function translationModel(): string
    {
        return OfficeTranslation::class;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('organisation')
            ->logOnly(['type', 'parent_id', 'head_leader_id', 'phone', 'email', 'latitude', 'longitude', 'cover_image_id', 'sort'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<Office, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Office, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return BelongsTo<Leader, $this>
     */
    public function head(): BelongsTo
    {
        return $this->belongsTo(Leader::class, 'head_leader_id');
    }

    /**
     * @return HasMany<Leader, $this>
     */
    public function leaders(): HasMany
    {
        return $this->hasMany(Leader::class);
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function coverImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_image_id');
    }

    /**
     * @return BelongsToMany<Post, $this>
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }

    /**
     * Ids of this office's ancestors, nearest first (stops on a loop).
     *
     * @return list<string>
     */
    public function ancestorIds(): array
    {
        $ids = [];
        $parentId = $this->parent_id;
        while ($parentId !== null && ! in_array($parentId, $ids, true) && $parentId !== $this->id && count($ids) < self::MAX_DEPTH + 1) {
            $ids[] = $parentId;
            $next = self::query()->whereKey($parentId)->value('parent_id');
            $parentId = is_string($next) ? $next : null;
        }

        return $ids;
    }

    public function hasMap(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /** The name for the admin: this language, its fallbacks, or any language the record has. */
    public function name(?string $locale = null): string
    {
        return (string) $this->translationOrAny($locale)?->getAttribute('name');
    }
}
