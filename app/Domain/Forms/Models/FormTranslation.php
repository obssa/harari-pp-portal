<?php

namespace App\Domain\Forms\Models;

use App\Domain\Content\Concerns\PublishedPerLanguage;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\Translation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One language of a form: its title and introduction, the thanks shown
 * after sending, and the automatic reply by email.
 *
 * @property string $form_id
 * @property string $title
 * @property ?string $intro
 * @property ?string $success_message
 * @property ?string $autoreply_subject
 * @property ?string $autoreply_body
 * @property ContentStatus $status
 * @property ?CarbonImmutable $published_at
 * @property-read Form $form
 */
class FormTranslation extends Translation
{
    use LogsActivity;
    use PublishedPerLanguage;

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'published_at' => 'immutable_datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('forms')
            ->logOnly(['locale', 'title', 'slug', 'intro', 'success_message', 'autoreply_subject', 'autoreply_body', 'status', 'published_at'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
