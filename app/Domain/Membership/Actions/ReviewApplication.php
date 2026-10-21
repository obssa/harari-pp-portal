<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Access\Support\OfficeScope;
use App\Domain\Membership\Enums\ApplicationStatus;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Mail\ApplicationDecisionMail;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipApplication;
use App\Domain\Membership\Models\PersonNote;
use App\Domain\Organisation\Enums\OfficeType;
use App\Domain\Organisation\Models\Office;
use App\Models\User;
use App\Services\Sms\SendSms;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * What a Membership Officer does with a submitted application (PLAN.md §5,
 * ADR-0045): approve it, which makes the person a member with a number;
 * reject it with a reason; hand it to the right woreda; or note something.
 * Only officers whose scope covers the application's woreda may act, and
 * the applicant hears the decision by SMS.
 */
final class ReviewApplication
{
    public function approve(MembershipApplication $application, User $actor): Member
    {
        $this->authorise($application, $actor);
        $this->assertOpen($application);

        $member = DB::transaction(function () use ($application, $actor): Member {
            $now = CarbonImmutable::now();
            $member = new Member([
                'application_id' => $application->id,
                'status' => MemberStatus::Active,
                'source' => Member::SOURCE_APPLICATION,
                'name' => (string) $application->name,
                'sex' => $application->sex,
                'birth_date' => $application->birth_date,
                'birth_year' => $application->birth_year,
                'id_type' => $application->id_type,
                'id_number' => $application->id_number,
                'id_hash' => $application->id_hash,
                'education' => $application->education,
                'occupation' => $application->occupation,
                'woreda_id' => $application->woreda_id,
                'kebele_id' => $application->kebele_id,
                'basic_org_id' => $application->basic_org_id,
                'address_note' => $application->address_note,
                'phone' => $application->phone,
                'phone_hash' => $application->phone_hash,
                'email' => $application->email,
                'email_hash' => $application->email_hash,
                'joined_on' => $now->toDateString(),
                'renewal_due_on' => $now->addMonthsNoOverflow(Member::RENEWAL_MONTHS)->toDateString(),
            ]);
            $member->member_no = Member::nextNumber();
            $member->site_id = $application->site_id;
            $member->save();

            $application->forceFill(['status' => ApplicationStatus::Approved, 'reviewer_id' => $actor->id, 'decided_at' => $now, 'reason' => null])->save();
            $this->log($application, $actor, PersonNote::DECISION, __('membership.note.approved', ['number' => $member->member_no]));

            return $member;
        });

        $this->tell($application, __('membership.sms.approved', ['number' => $member->member_no], $application->locale));
        $this->mail($application, $member->member_no);

        return $member;
    }

    public function reject(MembershipApplication $application, string $reason, User $actor): void
    {
        $this->authorise($application, $actor);
        $this->assertOpen($application);
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['reason' => __('membership.validation.reason')]);
        }

        $application->forceFill(['status' => ApplicationStatus::Rejected, 'reviewer_id' => $actor->id, 'decided_at' => CarbonImmutable::now(), 'reason' => $reason])->save();
        $this->log($application, $actor, PersonNote::DECISION, __('membership.note.rejected', ['reason' => $reason]));
        $this->tell($application, __('membership.sms.rejected', ['reference' => $application->reference], $application->locale));
        $this->mail($application, null);
    }

    public function assign(MembershipApplication $application, ?string $woredaId, User $actor): void
    {
        $this->authorise($application, $actor);
        $woreda = $woredaId !== null ? Office::query()->whereKey($woredaId)->where('type', OfficeType::Woreda->value)->first() : null;
        if ($woredaId !== null && $woreda === null) {
            throw ValidationException::withMessages(['woreda_id' => __('membership.validation.office')]);
        }

        $application->forceFill(['woreda_id' => $woreda?->id])->save();
        $this->log($application, $actor, PersonNote::ASSIGN, $woreda?->name() ?? '—');
    }

    public function note(MembershipApplication $application, string $text, User $actor): void
    {
        $this->authorise($application, $actor, 'applications.view');
        $text = trim($text);
        if ($text === '' || mb_strlen($text) > 2000) {
            throw ValidationException::withMessages(['note' => __('membership.validation.note')]);
        }
        $this->log($application, $actor, PersonNote::NOTE, $text);
    }

    /**
     * The full ID number, for checking it against the document. Each look is
     * recorded in the application's history and the audit log.
     */
    public function reveal(MembershipApplication $application, User $actor): string
    {
        $this->authorise($application, $actor);
        abort_if($application->id_number === null, 404);

        $this->log($application, $actor, PersonNote::REVEAL, null);
        activity('membership')->performedOn($application)->causedBy($actor)->event('revealed')->log('ID number viewed');

        return $application->id_number;
    }

    private function authorise(MembershipApplication $application, User $actor, string $permission = 'applications.review'): void
    {
        abort_unless(OfficeScope::covers($actor, $application->woreda_id, $permission), 403);
    }

    private function assertOpen(MembershipApplication $application): void
    {
        if ($application->status !== ApplicationStatus::Submitted) {
            throw ValidationException::withMessages(['status' => __('membership.validation.decided')]);
        }
    }

    private function log(MembershipApplication $application, User $actor, string $kind, ?string $body): void
    {
        $application->notes()->create(['user_id' => $actor->id, 'kind' => $kind, 'body' => $body]);
    }

    private function tell(MembershipApplication $application, string $text): void
    {
        if ($application->phone !== null) {
            SendSms::dispatch($application->phone, $text);
        }
    }

    private function mail(MembershipApplication $application, ?string $memberNo): void
    {
        if ($application->email === null) {
            return;
        }
        try {
            Mail::to($application->email)->locale($application->locale)->queue(new ApplicationDecisionMail(
                $application->reference, (string) $application->name, $memberNo !== null, $memberNo, $application->reason, $application->locale,
            ));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
