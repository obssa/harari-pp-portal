<?php

namespace App\Domain\Documents\Models;

use App\Domain\Content\Concerns\PublishedPerLanguage;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\Translation;
use App\Domain\Content\Support\BodyRenderer;
use App\Domain\Media\Models\Media;
use App\Domain\Search\Concerns\SearchableTranslation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One language of a document, with the file in that language and its
 * version number (ADR-0026).
 *
 * @property string $document_id
 * @property string $title
 * @property ?string $summary
 * @property ?array<string, mixed> $body
 * @property ?int $file_id
 * @property int $version
 * @property ?string $version_note
 * @property ContentStatus $status
 * @property ?CarbonImmutable $published_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Document $document
 * @property-read ?Media $file
 */
class DocumentTranslation extends Translation
{
    use LogsActivity;
    use PublishedPerLanguage;
    use SearchableTranslation;

    protected $attributes = ['status' => 'draft', 'version' => 1];

    protected function casts(): array
    {
        return [
            'body' => 'array',
            'version' => 'integer',
            'status' => ContentStatus::class,
            'published_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return list<?string>
     */
    public function searchableParts(): array
    {
        return [$this->title, $this->summary, BodyRenderer::toText($this->body)];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('documents')
            ->logOnly(['locale', 'title', 'slug', 'summary', 'file_id', 'version', 'version_note', 'status', 'published_at'])
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
    public function file(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'file_id');
    }
}
