<?php

namespace App\Domain\Membership\Models;

use App\Domain\Content\Concerns\BelongsToSite;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Organisation\Models\Office;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A member of the party (PLAN.md §5, §10, ADR-0045): from an approved
 * application, an imported roll or entered by an officer. Personal details
 * are encrypted; the audit log records status, office and dates only.
 *
 * @property string $id
 * @property string $member_no
 * @property ?string $application_id
 * @property ?int $import_id
 * @property MemberStatus $status
 * @property string $source
 * @property string $name
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
 * @property CarbonImmutable $joined_on
 * @property ?CarbonImmutable $renewed_on
 * @property ?CarbonImmutable $renewal_due_on
 * @property ?CarbonImmutable $renewal_reminded_on
 * @property ?CarbonImmutable $erased_at
 * @property ?CarbonImmutable $campaigns_opt_out_at
 * @property CarbonImmutable $created_at
 * @property-read ?Office $woreda
 * @property-read ?Office $kebele
 * @property-read ?Office $basicOrganisation
 * @property-read ?MembershipApplication $application
 */
class Member extends Model implements AuthenticatableContract
{
    use Authenticatable;
    use BelongsToSite;
    use HasUlids;
    use LogsActivity;

    public const SOURCE_APPLICATION = 'application';

    public const SOURCE_IMPORT = 'import';

    public const SOURCE_MANUAL = 'manual';

    /** Statuses that may sign in to the member portal. */
    public const PORTAL_STATUSES = [MemberStatus::Active, MemberStatus::Lapsed];

    /** Months between renewals (open question 4). */
    public const RENEWAL_MONTHS = 12;

    protected $fillable = [
        'application_id', 'import_id', 'status', 'source', 'name', 'sex', 'birth_date', 'birth_year', 'id_type', 'id_number', 'id_hash',
        'education', 'occupation', 'woreda_id', 'kebele_id', 'basic_org_id', 'address_note',
        'phone', 'phone_hash', 'email', 'email_hash', 'joined_on', 'renewed_on', 'renewal_due_on',
    ];

    protected $hidden = ['name', 'birth_date', 'id_number', 'phone', 'email', 'address_note'];

    protected function casts(): array
    {
        return [
            'status' => MemberStatus::class,
            'name' => 'encrypted',
            'birth_date' => 'encrypted',
            'birth_year' => 'integer',
            'id_number' => 'encrypted',
            'address_note' => 'encrypted',
            'phone' => 'encrypted',
            'email' => 'encrypted',
            'joined_on' => 'immutable_date',
            'renewed_on' => 'immutable_date',
            'renewal_due_on' => 'immutable_date',
            'renewal_reminded_on' => 'immutable_date',
            'erased_at' => 'immutable_datetime',
            'campaigns_opt_out_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * The next member number: HPP- and six digits, in order, never reused,
     * even after an import is rolled back (ADR-0047). Call inside a transaction.
     */
    public static function nextNumber(): string
    {
        $current = DB::table('sequences')->where('name', 'member_no')->lockForUpdate()->value('value');
        if ($current === null) {
            $last = DB::table('members')->where('member_no', 'like', 'HPP-%')->orderByDesc('member_no')->value('member_no');
            $current = $last !== null ? (int) substr((string) $last, 4) : 0;
            DB::table('sequences')->insert(['name' => 'member_no', 'value' => $current]);
        }
        $next = (int) $current + 1;
        DB::table('sequences')->where('name', 'member_no')->update(['value' => $next]);

        return 'HPP-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    /** Members sign in with a code by SMS, never a password (ADR-0049). */
    public function getAuthPassword(): string
    {
        return '';
    }

    /** ...and are never remembered on a device. */
    public function getRememberTokenName(): string
    {
        return '';
    }

    public function canUsePortal(): bool
    {
        return $this->erased_at === null && in_array($this->status, self::PORTAL_STATUSES, true);
    }

    /**
     * The check printed on the card's QR code (ADR-0049): the number alone does
     * not open the verification page, so numbers cannot be tried one by one.
     */
    public static function cardSignature(string $memberNo): string
    {
        return substr(hash_hmac('sha256', 'member-card:'.$memberNo, (string) config('app.key')), 0, 16);
    }

    public function cardUrl(?string $locale = null): string
    {
        return route('member.verify', ['locale' => $locale ?? app()->getLocale(), 'number' => $this->member_no, 'check' => self::cardSignature($this->member_no)]);
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
     * @return BelongsTo<MembershipApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(MembershipApplication::class, 'application_id');
    }

    /**
     * @return MorphMany<PersonNote, $this>
     */
    public function notes(): MorphMany
    {
        return $this->morphMany(PersonNote::class, 'notable')->latest('id');
    }

    /**
     * Devices the member is signed in to the member app on (ADR-0057).
     *
     * @return HasMany<MemberToken, $this>
     */
    public function appTokens(): HasMany
    {
        return $this->hasMany(MemberToken::class);
    }

    /**
     * The member's own event registrations: those made with the phone or
     * email on their record.
     *
     * @return Builder<EventRegistration>|null null when the record has neither
     */
    public function eventRegistrations(): ?Builder
    {
        $hashes = array_values(array_filter([$this->phone_hash, $this->email_hash]));
        if ($hashes === []) {
            return null;
        }

        return EventRegistration::query()
            ->where(fn (Builder $q) => $q->whereIn('phone_hash', $hashes)->orWhereIn('email_hash', $hashes))
            ->whereNull('erased_at');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('membership')
            ->logOnly(['member_no', 'status', 'woreda_id', 'kebele_id', 'basic_org_id', 'joined_on', 'renewed_on', 'renewal_due_on'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
