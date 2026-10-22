<?php

namespace App\Domain\Organisation\Models;

use App\Domain\Content\Concerns\BelongsToSite;
use App\Domain\Content\Concerns\HasTranslations;
use App\Domain\Content\Concerns\TracksEditors;
use App\Domain\Content\Contracts\TranslatableModel;
use App\Domain\Content\Models\Post;
use App\Domain\Media\Models\Media;
use App\Domain\Organisation\Enums\LeaderBody;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A person in the leadership directory (PLAN.md §4 About, §10): body,
 * position per language, rank, term, portrait, public contacts and social
 * links, the office or league they serve, and the posts they appear in.
 *
 * @property string $id
 * @property LeaderBody $body
 * @property ?string $office_id
 * @property ?string $league_id
 * @property int $rank
 * @property ?CarbonImmutable $term_start
 * @property ?CarbonImmutable $term_end
 * @property ?int $portrait_id
 * @property list<int>|null $photo_ids
 * @property ?string $phone
 * @property ?string $email
 * @property ?array<string, string> $socials
 * @property ?string $source_url
 * @property ?CarbonImmutable $source_date
 * @property-read ?Office $office
 * @property-read ?League $league
 * @property-read ?Media $portrait
 */
class Leader extends Model implements TranslatableModel
{
    use BelongsToSite;

    /** @use HasTranslations<LeaderTranslation> */
    use HasTranslations;

    use HasUlids;
    use LogsActivity;
    use SoftDeletes;
    use TracksEditors;

    /** Social networks a profile may link to, in display order. */
    public const SOCIALS = ['facebook', 'telegram', 'x', 'youtube', 'tiktok', 'linkedin'];

    protected $fillable = ['body', 'office_id', 'league_id', 'rank', 'term_start', 'term_end', 'portrait_id', 'photo_ids', 'phone', 'email', 'socials', 'source_url', 'source_date'];

    /** Most photos a profile's gallery shows (ADR-0071). */
    public const MAX_PHOTOS = 24;

    protected $attributes = ['rank' => 100];

    protected function casts(): array
    {
        return [
            'photo_ids' => 'array',
            'body' => LeaderBody::class,
            'rank' => 'integer',
            'term_start' => 'immutable_date',
            'term_end' => 'immutable_date',
            'socials' => 'array',
            'source_date' => 'immutable_date',
        ];
    }

    public static function translationModel(): string
    {
        return LeaderTranslation::class;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('organisation')
            ->logOnly(['body', 'office_id', 'league_id', 'rank', 'term_start', 'term_end', 'portrait_id', 'photo_ids', 'phone', 'email', 'socials'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<Office, $this>
     */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    /**
     * @return BelongsTo<League, $this>
     */
    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function portrait(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'portrait_id');
    }

    /**
     * The profile's photo gallery (ADR-0071), in the order an editor chose;
     * photos deleted from the library drop out.
     *
     * @return Collection<int, Media>
     */
    public function photos(): Collection
    {
        $ids = array_values(array_filter(array_map('intval', (array) ($this->photo_ids ?? []))));
        if ($ids === []) {
            return new Collection;
        }
        $found = Media::query()->whereKey($ids)->get()->keyBy('id');

        return new Collection(array_values(array_filter(array_map(fn (int $id): ?Media => $found->get($id), $ids))));
    }

    /**
     * Speeches, statements and news this person appears in.
     *
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
        foreach (self::SOCIALS as $network) {
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
