<?php

namespace App\Domain\Volunteers\Models;

use App\Domain\Content\Concerns\BelongsToSite;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\PersonNote;
use App\Domain\Organisation\Models\Office;
use App\Domain\Volunteers\Enums\VolunteerStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Someone who offered to help (PLAN.md §4, §5; ADR-0048). Contact details
 * and their message are encrypted; the audit log records only the status
 * and the woreda.
 *
 * @property string $id
 * @property VolunteerStatus $status
 * @property string $source
 * @property string $locale
 * @property string $name
 * @property ?string $phone
 * @property ?string $phone_hash
 * @property ?string $email
 * @property ?string $email_hash
 * @property ?string $woreda_id
 * @property list<string> $skills
 * @property list<string> $availability
 * @property list<string> $languages
 * @property ?string $message
 * @property ?string $member_id
 * @property ?CarbonImmutable $consented_at
 * @property ?CarbonImmutable $erased_at
 * @property ?CarbonImmutable $campaigns_opt_out_at
 * @property CarbonImmutable $created_at
 * @property-read ?Office $woreda
 * @property-read ?Member $member
 */
class Volunteer extends Model
{
    use BelongsToSite;
    use HasUlids;
    use LogsActivity;

    public const SOURCE_FORM = 'form';

    public const SOURCE_STAFF = 'staff';

    protected $fillable = ['status', 'source', 'locale', 'name', 'phone', 'phone_hash', 'email', 'email_hash', 'woreda_id',
        'skills', 'availability', 'languages', 'message', 'member_id', 'consented_at'];

    protected $hidden = ['name', 'phone', 'email', 'message'];

    protected function casts(): array
    {
        return [
            'status' => VolunteerStatus::class,
            'name' => 'encrypted',
            'phone' => 'encrypted',
            'email' => 'encrypted',
            'message' => 'encrypted',
            'skills' => 'array',
            'availability' => 'array',
            'languages' => 'array',
            'consented_at' => 'immutable_datetime',
            'erased_at' => 'immutable_datetime',
            'campaigns_opt_out_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Office, $this>
     */
    public function woreda(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'woreda_id');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return HasMany<VolunteerAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(VolunteerAssignment::class)->latest('on_date')->latest('id');
    }

    /**
     * @return MorphMany<PersonNote, $this>
     */
    public function notes(): MorphMany
    {
        return $this->morphMany(PersonNote::class, 'notable')->latest('id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('volunteers')->logOnly(['status', 'woreda_id', 'member_id'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
