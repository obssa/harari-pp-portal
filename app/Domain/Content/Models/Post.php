<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Concerns\BelongsToSite;
use App\Domain\Content\Concerns\HasTranslations;
use App\Domain\Content\Concerns\TracksEditors;
use App\Domain\Content\Contracts\TranslatableModel;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Enums\PostType;
use App\Domain\Media\Models\Media;
use App\Domain\Organisation\Models\Leader;
use App\Domain\Organisation\Models\League;
use App\Domain\Organisation\Models\Office;
use App\Domain\Press\Models\PressDistribution;
use App\Domain\Social\Models\SocialShare;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A news item, press release, statement, speech or announcement (PLAN.md
 * §7, §10). Text lives in one PostTranslation per language, each with its
 * own status and publish date.
 *
 * @property string $id
 * @property PostType $type
 * @property ?int $featured_image_id
 * @property bool $is_featured
 * @property bool $is_sticky
 * @property ?string $source_locale
 * @property int $view_count
 * @property ?string $source_url
 * @property ?string $source_name
 * @property ?CarbonImmutable $source_published_at
 * @property-read ?Media $featuredImage
 */
class Post extends Model implements TranslatableModel
{
    use BelongsToSite;

    /** @use HasTranslations<PostTranslation> */
    use HasTranslations;

    use HasUlids;
    use LogsActivity;
    use SoftDeletes;
    use TracksEditors;

    protected $fillable = ['type', 'featured_image_id', 'is_featured', 'is_sticky', 'source_locale'];

    protected $attributes = ['type' => 'news', 'is_featured' => false, 'is_sticky' => false];

    protected function casts(): array
    {
        return [
            'type' => PostType::class,
            'is_featured' => 'boolean',
            'is_sticky' => 'boolean',
            'view_count' => 'integer',
            'source_published_at' => 'immutable_datetime',
        ];
    }

    public static function translationModel(): string
    {
        return PostTranslation::class;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('content')
            ->logOnly(['type', 'featured_image_id', 'is_featured', 'is_sticky', 'source_locale'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_image_id');
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * @return BelongsToMany<Office, $this>
     */
    public function offices(): BelongsToMany
    {
        return $this->belongsToMany(Office::class);
    }

    /**
     * @return BelongsToMany<League, $this>
     */
    public function leagues(): BelongsToMany
    {
        return $this->belongsToMany(League::class);
    }

    /**
     * People this post is by or about (speeches, statements).
     *
     * @return BelongsToMany<Leader, $this>
     */
    public function leaders(): BelongsToMany
    {
        return $this->belongsToMany(Leader::class);
    }

    /**
     * @return MorphMany<Revision, $this>
     */
    public function revisions(): MorphMany
    {
        return $this->morphMany(Revision::class, 'revisionable');
    }

    /**
     * @return MorphMany<Comment, $this>
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function title(?string $locale = null): string
    {
        return (string) $this->translation($locale)?->title;
    }

    /**
     * Where this text was sent to the media (ADR-0054).
     *
     * @return HasMany<PressDistribution, $this>
     */
    public function pressDistributions(): HasMany
    {
        return $this->hasMany(PressDistribution::class);
    }

    /**
     * Where this post was shared on social networks (ADR-0059).
     *
     * @return HasMany<SocialShare, $this>
     */
    public function socialShares(): HasMany
    {
        return $this->hasMany(SocialShare::class);
    }

    /**
     * Posts that are live in exactly this language now.
     *
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    public function scopePublishedIn(Builder $query, string $locale): Builder
    {
        return $query->whereHas('translations', fn (Builder $q) => $q
            ->where('locale', $locale)
            ->where('status', ContentStatus::Published->value)
            ->where('published_at', '<=', now()));
    }
}
