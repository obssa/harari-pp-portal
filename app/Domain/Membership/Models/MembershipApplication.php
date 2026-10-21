<?php

namespace App\Domain\Membership\Models;

use App\Domain\Content\Concerns\BelongsToSite;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Membership\Enums\ApplicationStatus;
use App\Domain\Organisation\Models\Office;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * An application to join the party (PLAN.md §4, §10, ADR-0044). Personal
 * details are encrypted; the audit log records only the status and the
 * reviewer, never the details.
 *
 * @property string $id
 * @property string $reference
 * @property string $locale
 * @property ApplicationStatus $status
 * @property ?string $name
 * @property ?string $sex
 * @property ?string $birth_date
 * @property ?int $birth_year
 * @property ?string $id_type
 * @property ?string $id_number
 * @property ?string $id_hash
 * @property ?string $education
 * @property ?string $occupation
 * @property ?string $woreda_id
 * @property ?string $kebele_id
 * @property ?string $basic_org_id
 * @property ?string $address_note
 * @property ?string $phone
 * @property ?string $phone_hash
 * @property ?string $email
 * @property ?string $email_hash
 * @property ?string $code_hash
 * @property ?CarbonImmutable $code_expires_at
 * @property int $code_attempts
 * @property int $codes_sent
 * @property ?CarbonImmutable $code_sent_at
 * @property ?CarbonImmutable $consented_at
 * @property ?CarbonImmutable $phone_verified_at
 * @property ?CarbonImmutable $submitted_at
 * @property ?int $reviewer_id
 * @property ?CarbonImmutable $decided_at
 * @property ?string $reason
 * @property ?CarbonImmutable $erased_at
 * @property CarbonImmutable $created_at
 * @property-read ?Office $woreda
 * @property-read ?Office $kebele
 * @property-read ?Office $basicOrganisation
 * @property-read ?User $reviewer
 */
class MembershipApplication extends Model
{
    use BelongsToSite;
    use HasUlids;
    use LogsActivity;

    public const REFERENCE_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    protected $fillable = [
        'locale', 'name', 'sex', 'birth_date', 'birth_year', 'id_type', 'id_number', 'id_hash', 'education', 'occupation',
        'woreda_id', 'kebele_id', 'basic_org_id', 'address_note', 'phone', 'phone_hash', 'email', 'email_hash',
    ];

    protected $hidden = ['name', 'birth_date', 'id_number', 'phone', 'email', 'address_note', 'code_hash'];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'name' => 'encrypted',
            'birth_date' => 'encrypted',
            'birth_year' => 'integer',
            'id_number' => 'encrypted',
            'address_note' => 'encrypted',
            'phone' => 'encrypted',
            'email' => 'encrypted',
            'reason' => 'encrypted',
            'code_attempts' => 'integer',
            'codes_sent' => 'integer',
            'code_expires_at' => 'immutable_datetime',
            'code_sent_at' => 'immutable_datetime',
            'consented_at' => 'immutable_datetime',
            'phone_verified_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'decided_at' => 'immutable_datetime',
            'erased_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** The keyed hash used for every lookup of a person's details (same key as registrations and tickets). */
    public static function hash(string $value): string
    {
        return EventRegistration::hash($value);
    }

    public static function newReference(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= self::REFERENCE_ALPHABET[random_int(0, strlen(self::REFERENCE_ALPHABET) - 1)];
            }
            $reference = 'APP-'.substr($code, 0, 4).'-'.substr($code, 4);
        } while (self::query()->withoutGlobalScopes()->where('reference', $reference)->exists());

        return $reference;
    }

    /** A reference as people type it: any case, spaces or missing dashes. */
    public static function normaliseReference(string $typed): string
    {
        $plain = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $typed));
        if (str_starts_with($plain, 'APP')) {
            $plain = substr($plain, 3);
        }

        if (strlen($plain) !== 8 || strspn($plain, self::REFERENCE_ALPHABET) !== 8) {
            return '';
        }

        return 'APP-'.substr($plain, 0, 4).'-'.substr($plain, 4);
    }

    /**
     * @return BelongsTo<Office, $this>
     */
    public function woreda(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'woreda_id');
    }

    /**
     * @return BelongsTo<Office, $this>
     */
    public function kebele(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'kebele_id');
    }

    /**
     * @return BelongsTo<Office, $this>
     */
    public function basicOrganisation(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'basic_org_id');
    }

    /**
     * @return MorphMany<PersonNote, $this>
     */
    public function notes(): MorphMany
    {
        return $this->morphMany(PersonNote::class, 'notable')->latest('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('membership')->logOnly(['status', 'reviewer_id', 'woreda_id'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
