<?php

namespace App\Console\Commands;

use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\PersonNote;
use App\Services\Calendar\EthiopianCalendar;
use App\Services\Sms\SendSms;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Renewals (ADR-0046): active members whose renewal falls due within the
 * reminder window get one SMS for that period; members still not renewed
 * well after the due date become lapsed, which the history records.
 */
final class MembershipRenewalsCommand extends Command
{
    protected $signature = 'membership:renewals';

    protected $description = 'Remind members whose renewal is due and mark long-overdue memberships as lapsed';

    public function handle(EthiopianCalendar $calendar): int
    {
        $today = CarbonImmutable::today();
        $window = max(1, (int) config('portal.membership_reminder_days'));
        $grace = max(0, (int) config('portal.membership_grace_days'));
        $reminded = 0;
        $lapsed = 0;

        Member::query()->withoutGlobalScopes()
            ->where('status', MemberStatus::Active->value)->whereNull('erased_at')
            ->whereBetween('renewal_due_on', [$today->toDateString(), $today->addDays($window)->toDateString()])
            ->whereNull('renewal_reminded_on')
            ->with('application')
            ->chunkById(200, function (Collection $members) use ($calendar, $today, &$reminded): void {
                /** @var Member $member */
                foreach ($members as $member) {
                    if ($member->phone !== null && $member->renewal_due_on !== null) {
                        $locale = $member->application->locale ?? config('app.locale');
                        SendSms::dispatch($member->phone, __('members.sms.renewal', [
                            'number' => $member->member_no,
                            'date' => $calendar->format($member->renewal_due_on, $locale),
                        ], $locale));
                        $reminded++;
                    }
                    // Marked even without a phone, so the member is not looked at again every day.
                    $member->forceFill(['renewal_reminded_on' => $today->toDateString()])->saveQuietly();
                }
            });

        Member::query()->withoutGlobalScopes()
            ->where('status', MemberStatus::Active->value)->whereNull('erased_at')
            ->where('renewal_due_on', '<', $today->subDays($grace)->toDateString())
            ->chunkById(200, function (Collection $members) use ($calendar, &$lapsed): void {
                /** @var Member $member */
                foreach ($members as $member) {
                    $member->forceFill(['status' => MemberStatus::Lapsed])->save();
                    $member->notes()->create(['user_id' => null, 'kind' => PersonNote::STATUS,
                        'body' => __('members.note.lapsed', ['date' => $member->renewal_due_on !== null ? $calendar->format($member->renewal_due_on) : '—'])]);
                    $lapsed++;
                }
            });

        $this->components->info("Renewal reminders sent: {$reminded}; memberships lapsed: {$lapsed}");

        return self::SUCCESS;
    }
}
