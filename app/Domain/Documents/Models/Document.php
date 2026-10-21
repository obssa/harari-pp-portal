<?php

namespace App\Domain\Documents\Models;

use App\Domain\Content\Concerns\BelongsToSite;
use App\Domain\Content\Concerns\HasTranslations;
use App\Domain\Content\Concerns\TracksEditors;
use App\Domain\Content\Contracts\TranslatableModel;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Organisation\Models\League;
use App\Domain\Organisation\Models\Office;
use App\Domain\Translation\Support\Locales;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A document in the library (PLAN.md §4 Resources, ADR-0026): a programme,
 * bylaw, report, speech … or a vacancy, tender or notice with a deadline.
 * Each language has its own title and, ideally, its own file.
 *
 * @property string $id
 * @property DocumentType $type
 * @property ?int $year
 * @property ?CarbonImmutable $issued_on
 * @property ?CarbonImmutable $deadline_at
 * @property ?string $office_id
 * @property ?string $league_id
 * @property string $access
 * @property int $download_count
 * @property-read ?Office $office
 * @property-read ?League $league
 */
class Document extends Model implements TranslatableModel
{
    use BelongsToSite;

    /** @use HasTranslations<DocumentTranslation> */
    use HasTranslations;

    use HasUlids;
    use LogsActivity;
    use SoftDeletes;
    use TracksEditors;

    public const ACCESS_PUBLIC = 'public';

    /** For the member portal (Phase 6); never shown or served until then. */
    public const ACCESS_MEMBERS = 'members';

    protected $fillable = ['type', 'year', 'issued_on', 'deadline_at', 'office_id', 'league_id', 'access'];

    protected $attributes = ['access' => self::ACCESS_PUBLIC, 'download_count' => 0];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'year' => 'integer',
            'issued_on' => 'immutable_date',
            'deadline_at' => 'immutable_datetime',
            'download_count' => 'integer',
        ];
    }

    public static function translationModel(): string
    {
        return DocumentTranslation::class;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('documents')
            ->logOnly($this->fillable)
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
     * @return HasMany<DocumentVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('version');
    }

    /**
     * @return BelongsToMany<Document, $this>
     */
    public function related(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'document_related', 'document_id', 'related_id')
            ->withPivot('sort')->orderByPivot('sort');
    }

    /**
     * Whether a library file belongs to a members-only document (ADR-0049), in
     * any language or earlier version; such files are served only in the member
     * portal.
     */
    public static function isMembersOnlyFile(int $mediaId): bool
    {
        $members = fn ($query) => $query->where('access', self::ACCESS_MEMBERS);

        return DocumentTranslation::query()->where('file_id', $mediaId)->whereHas('document', $members)->exists()
            || DocumentVersion::query()->where('media_id', $mediaId)->whereHas('document', $members)->exists();
    }

    /**
     * Only what the public may see: members-only documents are in the member portal (ADR-0049).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePublicAccess(Builder $query): Builder
    {
        return $query->where('access', self::ACCESS_PUBLIC);
    }

    /**
     * @param  Builder<static>  $query
     * @param  list<DocumentType>  $types
     * @return Builder<static>
     */
    public function scopeOfTypes(Builder $query, array $types): Builder
    {
        return $query->whereIn('type', array_map(fn (DocumentType $type): string => $type->value, $types));
    }

    /**
     * Vacancies and tenders still open: no deadline, or one still ahead.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('deadline_at')->orWhere('deadline_at', '>=', CarbonImmutable::now('UTC')));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereNotNull('deadline_at')->where('deadline_at', '<', CarbonImmutable::now('UTC'));
    }

    public function isClosed(): bool
    {
        return $this->deadline_at !== null && $this->deadline_at->isPast();
    }

    /**
     * The file a reader of this language gets: their own, otherwise that of
     * the first other published language that has one (Amharic first).
     */
    public function fileFor(string $locale): ?DocumentTranslation
    {
        $order = array_values(array_unique([$locale, ...(array) config("portal.content_fallbacks.{$locale}", []), 'am', 'en', ...Locales::all()]));
        foreach ($order as $candidate) {
            $translation = $this->translation($candidate, fallback: false);
            if ($translation === null || ($candidate !== $locale && ! $translation->isLive())) {
                continue;
            }
            if ($translation->file_id !== null && $translation->file !== null) {
                return $translation;
            }
        }

        return null;
    }

    /** The name for the admin: this language, its fallbacks, or any language. */
    public function title(?string $locale = null): string
    {
        return (string) $this->translationOrAny($locale)?->getAttribute('title');
    }
}
