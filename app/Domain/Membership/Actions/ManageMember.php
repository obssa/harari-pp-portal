<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Access\Support\OfficeScope;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\PersonNote;
use App\Models\User;
use App\Services\Calendar\EthiopianCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * What officers do with a member (PLAN.md §5, ADR-0046): renew the
 * membership for another period, change the status with a reason, keep
 * notes and look at the full ID number. Each step is in the member's
 * history; only officers whose scope covers the member's woreda act.
 */
final class ManageMember
{
    /** Renews from the due date, or from today when the membership has already run out. */
    public function renew(Member $member, User $actor): CarbonImmutable
    {
        $this->authorise($member, $actor, 'members.update');
        if (! in_array($member->status, [MemberStatus::Active, MemberStatus::Lapsed], true)) {
            throw ValidationException::withMessages(['status' => __('members.validation.cannot_renew')]);
        }

        $today = CarbonImmutable::today();
        $from = $member->renewal_due_on !== null && $member->renewal_due_on->greaterThan($today) ? $member->renewal_due_on : $today;
        $due = $from->addMonthsNoOverflow(Member::RENEWAL_MONTHS);
        $member->forceFill([
            'status' => MemberStatus::Active,
            'renewed_on' => $today->toDateString(),
            'renewal_due_on' => $due->toDateString(),
            'renewal_reminded_on' => null,
        ])->save();
        $this->log($member, $actor, PersonNote::STATUS, __('members.note.renewed', ['date' => app(EthiopianCalendar::class)->format($due)]));

        return $due;
    }

    public function changeStatus(Member $member, MemberStatus $status, string $reason, User $actor): void
    {
        $this->authorise($member, $actor, 'members.update');
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['reason' => __('membership.validation.reason')]);
        }
        if ($status === $member->status) {
            return;
        }

        $from = $member->status;
        $member->forceFill(['status' => $status])->save();
        $this->log($member, $actor, PersonNote::STATUS, __('members.note.status', ['from' => $from->label(), 'to' => $status->label(), 'reason' => $reason]));
    }

    public function note(Member $member, string $text, User $actor): void
    {
        $this->authorise($member, $actor, 'members.view');
        $text = trim($text);
        if ($text === '' || mb_strlen($text) > 2000) {
            throw ValidationException::withMessages(['note' => __('membership.validation.note')]);
        }
        $this->log($member, $actor, PersonNote::NOTE, $text);
    }

    /** The full ID number, recorded in the history and the audit log. */
    public function reveal(Member $member, User $actor): string
    {
        $this->authorise($member, $actor, 'members.update');
        abort_if($member->id_number === null, 404);

        $this->log($member, $actor, PersonNote::REVEAL, null);
        activity('membership')->performedOn($member)->causedBy($actor)->event('revealed')->log('ID number viewed');

        return $member->id_number;
    }

    /** Signs the member out of the member app on every device, for example after a lost phone (ADR-0057). */
    public function signOutApps(Member $member, User $actor): int
    {
        $this->authorise($member, $actor, 'members.update');
        $count = app(IssueMemberToken::class)->revokeAll($member);
        $this->log($member, $actor, PersonNote::UPDATE, __('members.note.apps_signed_out', ['count' => $count]));

        return $count;
    }

    private function authorise(Member $member, User $actor, string $permission): void
    {
        abort_unless($member->erased_at === null && OfficeScope::covers($actor, $member->woreda_id, $permission), 403);
    }

    private function log(Member $member, ?User $actor, string $kind, ?string $body): void
    {
        $member->notes()->create(['user_id' => $actor?->id, 'kind' => $kind, 'body' => $body]);
    }
}
