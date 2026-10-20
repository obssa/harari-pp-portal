<?php

namespace App\Domain\Documents\Models;

use App\Domain\Media\Models\Media;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * An earlier file of one language of a document, kept when a newer file
 * replaced it (PLAN.md §4: version history).
 *
 * @property int $id
 * @property string $document_id
 * @property string $locale
 * @property ?int $media_id
 * @property int $version
 * @property ?string $note
 * @property CarbonImmutable $replaced_at
 * @property ?int $replaced_by
 * @property-read Document $document
 * @property-read ?Media $media
 * @property-read ?User $replacedBy
 */
class DocumentVersion extends Model
{
    use LogsActivity;

    protected $fillable = ['locale', 'media_id', 'version', 'note', 'replaced_at', 'replaced_by'];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'replaced_at' => 'immutable_datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('documents')
            ->logOnly(['locale', 'media_id', 'version', 'note'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replaced_by');
    }
}
