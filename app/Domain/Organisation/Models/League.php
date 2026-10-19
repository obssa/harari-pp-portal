<?php

namespace App\Domain\Organisation\Models;

use App\Domain\Content\Concerns\BelongsToSite;
use App\Domain\Content\Concerns\HasTranslations;
use App\Domain\Content\Concerns\TracksEditors;
use App\Domain\Content\Contracts\TranslatableModel;
use App\Domain\Content\Models\Post;
use App\Domain\Media\Models\Media;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A league or forum (PLAN.md §3 Leagues & Forums: Youth League, Women's
 * League, forums): its own page with leadership, news and contacts.
 *
 * @property string $id
 * @property ?string $head_leader_id
 * @property ?int $logo_id
 * @property ?int $cover_image_id
 * @property ?string $phone
 * @property ?string $email
 * @property ?array<string, string> $socials
 * @property int $sort
 * @property-read ?Leader $head
 * @property-read ?Media $logo
 * @property-read ?Media $coverImage
 */
class League extends Model implements TranslatableModel
{
    use BelongsToSite;

    /** @use HasTranslations<LeagueTranslation> */
    use HasTranslations;

    use HasUlids;
    use LogsActivity;
    use SoftDeletes;
    use TracksEditors;

    protected $fillable = ['head_leader_id', 'logo_id', 'cover_image_id', 'phone', 'email', 'socials', 'sort'];

    protected $attributes = ['sort' => 0];

    protected function casts(): array
    {
        return [
            'socials' => 'array',
            'sort' => 'integer',
        ];
    }

    public static function translationModel(): string
    {
        return LeagueTranslation::class;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('organisation')
            ->logOnly(['head_leader_id', 'logo_id', 'cover_image_id', 'phone', 'email', 'socials', 'sort'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
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
    public function logo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_id');
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
     * Social links in display order, https addresses only.
     *
     * @return array<string, string>
     */
    public function socialLinks(): array
    {
        $links = [];
        foreach (Leader::SOCIALS as $network) {
            $url = $this->socials[$network] ?? null;
            if (is_string($url) && str_starts_with($url, 'https://')) {
                $links[$network] = $url;
            }
        }

        return $links;
    }

    /** The name for the admin: this language, its fallbacks, or any language the record has. */
    public function name(?string $locale = null): string
    {
        return (string) $this->translationOrAny($locale)?->getAttribute('name');
    }
}
