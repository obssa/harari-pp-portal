<?php

namespace App\Domain\Forms\Models;

use App\Domain\Content\Concerns\BelongsToSite;
use App\Domain\Content\Concerns\HasTranslations;
use App\Domain\Content\Concerns\TracksEditors;
use App\Domain\Content\Contracts\TranslatableModel;
use App\Domain\Forms\Enums\FormType;
use App\Domain\Forms\Support\FormField;
use App\Domain\Organisation\Models\Office;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A public form built in the admin (PLAN.md §7 forms builder, ADR-0028):
 * its own questions after the name, email and phone, where submissions go,
 * and how soon they should be answered.
 *
 * @property string $id
 * @property FormType $type
 * @property ?string $office_id
 * @property ?string $notify_email
 * @property ?list<array<string, mixed>> $fields
 * @property bool $anonymous_allowed
 * @property int $response_days
 * @property bool $active
 * @property bool $on_contact_page
 * @property-read ?Office $office
 */
class Form extends Model implements TranslatableModel
{
    use BelongsToSite;

    /** @use HasTranslations<FormTranslation> */
    use HasTranslations;

    use HasUlids;
    use LogsActivity;
    use SoftDeletes;
    use TracksEditors;

    /** Most questions on one form. */
    public const MAX_FIELDS = 30;

    protected $fillable = ['type', 'office_id', 'notify_email', 'fields', 'anonymous_allowed', 'response_days', 'active', 'on_contact_page'];

    protected $attributes = ['type' => 'contact', 'anonymous_allowed' => false, 'response_days' => 3, 'active' => true, 'on_contact_page' => false];

    protected function casts(): array
    {
        return [
            'type' => FormType::class,
            'fields' => 'array',
            'anonymous_allowed' => 'boolean',
            'response_days' => 'integer',
            'active' => 'boolean',
            'on_contact_page' => 'boolean',
        ];
    }

    public static function translationModel(): string
    {
        return FormTranslation::class;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('forms')
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
     * @return HasMany<FormSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    /**
     * The questions, in order.
     *
     * @return list<FormField>
     */
    public function questions(): array
    {
        return array_values(array_filter(array_map(
            fn (array $field): ?FormField => FormField::fromArray($field),
            $this->fields ?? [],
        )));
    }

    public function hasFileQuestions(): bool
    {
        foreach ($this->questions() as $field) {
            if ($field->type === FormField::FILE) {
                return true;
            }
        }

        return false;
    }

    /** The name for the admin: this language, its fallbacks, or any language. */
    public function title(?string $locale = null): string
    {
        return (string) $this->translationOrAny($locale)?->getAttribute('title');
    }
}
