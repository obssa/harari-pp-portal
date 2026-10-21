<?php

namespace App\Domain\Volunteers\Actions;

use App\Domain\Access\Support\OfficeScope;
use App\Domain\Events\Models\Event;
use App\Domain\Membership\Models\PersonNote;
use App\Domain\Volunteers\Enums\AssignmentStatus;
use App\Domain\Volunteers\Enums\VolunteerStatus;
use App\Domain\Volunteers\Models\Volunteer;
use App\Domain\Volunteers\Models\VolunteerAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * What coordinators do with a volunteer (ADR-0048): set the status, keep
 * notes, and plan tasks at events or on their own with the hours given.
 * Giving a new volunteer a first task makes them active.
 */
final class ManageVolunteer
{
    public function changeStatus(Volunteer $volunteer, VolunteerStatus $status, ?string $reason, User $actor): void
    {
        $this->authorise($volunteer, $actor, 'volunteers.manage');
        if ($status === $volunteer->status) {
            return;
        }
        $reason = trim((string) $reason);
        if (mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['reason' => __('membership.validation.reason')]);
        }

        $from = $volunteer->status;
        $volunteer->forceFill(['status' => $status])->save();
        $volunteer->notes()->create(['user_id' => $actor->id, 'kind' => PersonNote::STATUS,
            'body' => __('volunteers.note.status', ['from' => $from->label(), 'to' => $status->label()]).($reason !== '' ? ': '.$reason : '')]);
    }

    public function note(Volunteer $volunteer, string $text, User $actor): void
    {
        $this->authorise($volunteer, $actor, 'volunteers.view');
        $text = trim($text);
        if ($text === '' || mb_strlen($text) > 2000) {
            throw ValidationException::withMessages(['note' => __('membership.validation.note')]);
        }
        $volunteer->notes()->create(['user_id' => $actor->id, 'kind' => PersonNote::NOTE, 'body' => $text]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveAssignment(Volunteer $volunteer, ?VolunteerAssignment $assignment, array $data, User $actor): VolunteerAssignment
    {
        $this->authorise($volunteer, $actor, 'volunteers.manage');
        $errors = [];
        $event = filled($data['event_id'] ?? null) ? Event::query()->find((string) $data['event_id']) : null;
        if (filled($data['event_id'] ?? null) && $event === null) {
            $errors['event_id'] = __('volunteers.validation.event');
        }
        $task = trim((string) ($data['task'] ?? ''));
        if ($task === '' && $event !== null) {
            $task = $event->title();
        }
        if ($task === '' || mb_strlen($task) > 120) {
            $errors['task'] = __('volunteers.validation.task');
        }
        $status = AssignmentStatus::tryFrom((string) ($data['status'] ?? AssignmentStatus::Planned->value));
        if ($status === null) {
            $errors['status'] = __('volunteers.validation.status');
        }
        $hours = filled($data['hours'] ?? null) ? (float) $data['hours'] : null;
        if ($hours !== null && ($hours < 0 || $hours > 999)) {
            $errors['hours'] = __('volunteers.validation.hours');
        }
        try {
            $onDate = filled($data['on_date'] ?? null) ? CarbonImmutable::parse((string) $data['on_date'])->toDateString() : $event?->starts_at->toDateString();
        } catch (Throwable) {
            $errors['on_date'] = __('membership.validation.birth_date');
            $onDate = null;
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $assignment ??= new VolunteerAssignment(['user_id' => $actor->id]);
        $assignment->fill(['event_id' => $event?->id, 'task' => $task, 'on_date' => $onDate, 'status' => $status, 'hours' => $hours]);
        $volunteer->assignments()->save($assignment);

        if ($volunteer->status === VolunteerStatus::New && $status !== AssignmentStatus::Cancelled) {
            $volunteer->forceFill(['status' => VolunteerStatus::Active])->save();
        }

        return $assignment;
    }

    /** Hours given in assignments marked done. */
    public static function hours(Volunteer $volunteer): float
    {
        return (float) $volunteer->assignments()->where('status', AssignmentStatus::Done->value)->sum('hours');
    }

    private function authorise(Volunteer $volunteer, User $actor, string $permission): void
    {
        abort_unless($volunteer->erased_at === null && OfficeScope::covers($actor, $volunteer->woreda_id, $permission), 403);
    }
}
