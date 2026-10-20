<?php

namespace App\Domain\Forms\Models;

use App\Domain\Content\Concerns\BelongsToSite;
use App\Domain\Forms\Enums\SubmissionStatus;
use App\Domain\Organisation\Models\Office;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * What someone sent with a form: a ticket with a random reference
 * (ADR-0028). The person's details and answers are encrypted at rest; the
 * audit log records the handling, never the content.
 *
 * @property string $id
 * @property string $form_id
 * @property ?string $office_id
 * @property string $locale
 * @property string $reference
 * @property ?string $name
 * @property ?string $email
 * @property ?string $phone
 * @property array<string, mixed> $answers
 * @property ?list<array{field: string, path: string, name: string, size: int, mime: string}> $files
 * @property SubmissionStatus $status
 * @property ?int $assignee_id
 * @property ?CarbonImmutable $due_at
 * @property ?string $email_hash
 * @property ?string $phone_hash
 * @property ?CarbonImmutable $erased_at
 * @property ?CarbonImmutable $first_response_at
 * @property ?CarbonImmutable $resolved_at
 * @property CarbonImmutable $created_at
 * @property-read Form $form
 * @property-read ?Office $office
 * @property-read ?User $assignee
 * @property-read Collection<int, SubmissionNote> $notes
 */
class FormSubmission extends Model
{
    use BelongsToSite;
    use HasUlids;
    use LogsActivity;

    /** Letters and digits that cannot be mistaken for each other. */
    public const REFERENCE_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    protected $fillable = ['form_id', 'office_id', 'locale', 'reference', 'name', 'email', 'phone', 'email_hash', 'phone_hash', 'answers', 'files', 'status', 'due_at'];

    protected $hidden = ['name', 'email', 'phone', 'answers'];

    protected $attributes = ['status' => 'new'];

    protected function casts(): array
    {
        return [
            'name' => 'encrypted',
            'email' => 'encrypted',
            'phone' => 'encrypted',
            'answers' => 'encrypted:array',
            'files' => 'array',
            'status' => SubmissionStatus::class,
            'due_at' => 'immutable_datetime',
            'first_response_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('forms')
            ->logOnly(['status', 'assignee_id', 'office_id', 'first_response_at', 'resolved_at'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Office, $this>
     */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return HasMany<SubmissionNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(SubmissionNote::class)->latest('id');
    }

    public static function newReference(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= self::REFERENCE_ALPHABET[random_int(0, strlen(self::REFERENCE_ALPHABET) - 1)];
            }
            $reference = 'HPP-'.substr($code, 0, 4).'-'.substr($code, 4);
        } while (self::query()->where('reference', $reference)->exists());

        return $reference;
    }

    /** A reference as people type it: any case, spaces or missing dashes. */
    public static function normaliseReference(string $typed): string
    {
        $plain = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $typed));
        if (str_starts_with($plain, 'HPP')) {
            $plain = substr($plain, 3);
        }

        if (strlen($plain) !== 8 || strspn($plain, self::REFERENCE_ALPHABET) !== 8) {
            return '';
        }

        return 'HPP-'.substr($plain, 0, 4).'-'.substr($plain, 4);
    }

    public function isOverdue(): bool
    {
        return $this->status->isOpen() && $this->first_response_at === null && $this->due_at !== null && $this->due_at->isPast();
    }
}
