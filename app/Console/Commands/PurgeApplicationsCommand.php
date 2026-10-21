<?php

namespace App\Console\Commands;

use App\Domain\Membership\Actions\ImportMembers;
use App\Domain\Membership\Enums\ApplicationStatus;
use App\Domain\Membership\Models\MembershipApplication;
use App\Domain\Membership\Models\PersonNote;
use App\Domain\Volunteers\Enums\VolunteerStatus;
use App\Domain\Volunteers\Models\Volunteer;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Membership applications that were never sent are deleted after a week;
 * rejected and withdrawn ones some months after the decision (ADR-0044).
 * Approved applications stay with the member record. Checked roll imports
 * nobody applied within a week lose their rows (ADR-0047). Volunteer
 * sign-ups nobody followed up within a year are deleted (ADR-0048).
 */
final class PurgeApplicationsCommand extends Command
{
    public const UNFINISHED_DAYS = 7;

    public const UNFOLLOWED_VOLUNTEER_DAYS = 365;

    protected $signature = 'membership:purge-applications';

    protected $description = 'Delete unfinished membership applications and old rejected or withdrawn ones';

    public function handle(): int
    {
        $now = CarbonImmutable::now();
        $unfinished = $this->purge(MembershipApplication::query()->withoutGlobalScopes()
            ->where('status', ApplicationStatus::Started->value)
            ->where('updated_at', '<', $now->subDays(self::UNFINISHED_DAYS))
            ->pluck('id')->all());
        $closed = $this->purge(MembershipApplication::query()->withoutGlobalScopes()
            ->whereIn('status', [ApplicationStatus::Rejected->value, ApplicationStatus::Withdrawn->value])
            ->where('decided_at', '<', $now->subDays(max(30, (int) config('portal.closed_application_retention_days'))))
            ->pluck('id')->all());

        $imports = ImportMembers::purgeStale();
        $volunteers = $this->purgeVolunteers($now);

        $this->components->info("Unfinished applications deleted: {$unfinished}; closed applications deleted: {$closed}; stale roll imports discarded: {$imports}; old volunteer sign-ups deleted: {$volunteers}");

        return self::SUCCESS;
    }

    private function purgeVolunteers(CarbonImmutable $now): int
    {
        $ids = Volunteer::query()->withoutGlobalScopes()
            ->where('status', VolunteerStatus::New->value)
            ->where('created_at', '<', $now->subDays(self::UNFOLLOWED_VOLUNTEER_DAYS))
            ->pluck('id')->all();
        foreach (array_chunk($ids, 500) as $chunk) {
            PersonNote::query()->where('notable_type', Volunteer::class)->whereIn('notable_id', $chunk)->delete();
            Volunteer::query()->withoutGlobalScopes()->whereKey($chunk)->delete();
        }

        return count($ids);
    }

    /**
     * Deletes the applications with their history (ADR-0046).
     *
     * @param  list<string>  $ids
     */
    private function purge(array $ids): int
    {
        foreach (array_chunk($ids, 500) as $chunk) {
            PersonNote::query()->where('notable_type', MembershipApplication::class)->whereIn('notable_id', $chunk)->delete();
            MembershipApplication::query()->withoutGlobalScopes()->whereKey($chunk)->delete();
        }

        return count($ids);
    }
}
