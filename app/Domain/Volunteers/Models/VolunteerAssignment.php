<?php

namespace App\Domain\Volunteers\Models;

use App\Domain\Events\Models\Event;
use App\Domain\Volunteers\Enums\AssignmentStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A task a volunteer takes on, at an event or on its own, and the hours
 * given once done (ADR-0048).
 *
 * @property int $id
 * @property string $volunteer_id
 * @property ?string $event_id
 * @property string $task
 * @property ?CarbonImmutable $on_date
 * @property AssignmentStatus $status
 * @property ?string $hours
 * @property ?int $user_id
 * @property CarbonImmutable $created_at
 * @property-read Volunteer $volunteer
 * @property-read ?Event $event
 * @property-read ?User $user
 */
class VolunteerAssignment extends Model
{
    use LogsActivity;

    protected $fillable = ['event_id', 'task', 'on_date', 'status', 'hours', 'user_id'];

    protected function casts(): array
    {
        return [
            'status' => AssignmentStatus::class,
            'on_date' => 'immutable_date',
            'hours' => 'decimal:2',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Volunteer, $this>
     */
    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(Volunteer::class);
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('volunteers')->logOnly(['volunteer_id', 'event_id', 'task', 'on_date', 'status', 'hours'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
