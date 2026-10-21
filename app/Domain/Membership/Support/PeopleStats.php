<?php

namespace App\Domain\Membership\Support;

use App\Domain\Access\Support\OfficeScope;
use App\Domain\Membership\Enums\ApplicationStatus;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipApplication;
use App\Domain\Organisation\Models\Office;
use App\Domain\Volunteers\Enums\AssignmentStatus;
use App\Domain\Volunteers\Enums\VolunteerStatus;
use App\Domain\Volunteers\Models\Volunteer;
use App\Domain\Volunteers\Models\VolunteerAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Counts about people for the dashboard and the membership report (PLAN.md
 * §7, ADR-0050): only numbers, never anyone's details, and only over the
 * woredas the viewer looks after. Counted in the database; names and other
 * encrypted details are never read.
 */
final class PeopleStats
{
    public const DIMENSIONS = ['woreda', 'sex', 'age_band', 'status', 'source', 'joined_month'];

    public const AGE_BANDS = ['18-24', '25-34', '35-44', '45-54', '55-64', '65+'];

    /** Downloads show smaller counts as "<5", so no one can be picked out in a small group. */
    public const SMALL = 5;

    public function __construct(
        private readonly User $viewer,
        private readonly ?string $officeId = null,
    ) {}

    /**
     * @return Builder<Member>
     */
    public function members(): Builder
    {
        $query = OfficeScope::apply(Member::query()->whereNull('erased_at'), $this->viewer, 'members.view');

        return $this->officeId === null ? $query : $query->where('woreda_id', $this->officeId);
    }

    /**
     * @return Builder<MembershipApplication>
     */
    public function applications(): Builder
    {
        $query = OfficeScope::apply(MembershipApplication::query()->whereNull('erased_at'), $this->viewer, 'applications.view');

        return $this->officeId === null ? $query : $query->where('woreda_id', $this->officeId);
    }

    /**
     * @return Builder<Volunteer>
     */
    public function volunteers(): Builder
    {
        $query = OfficeScope::apply(Volunteer::query()->whereNull('erased_at'), $this->viewer, 'volunteers.view');

        return $this->officeId === null ? $query : $query->where('woreda_id', $this->officeId);
    }

    public function activeMembers(): int
    {
        return $this->members()->where('status', MemberStatus::Active->value)->count();
    }

    public function joined(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return $this->members()->whereBetween('joined_on', [$from->toDateString(), $to->toDateString()])->count();
    }

    public function waitingApplications(): int
    {
        return $this->applications()->where('status', ApplicationStatus::Submitted->value)->count();
    }

    /**
     * Applications started in the period and how far they got.
     *
     * @return array{started: int, sent: int, approved: int, rejected: int}
     */
    public function funnel(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $base = fn (): Builder => $this->applications()->whereBetween('created_at', [$from, $to]);

        return [
            'started' => $base()->count(),
            'sent' => $base()->whereNotNull('submitted_at')->count(),
            'approved' => $base()->where('status', ApplicationStatus::Approved->value)->count(),
            'rejected' => $base()->where('status', ApplicationStatus::Rejected->value)->count(),
        ];
    }

    /**
     * @return array{active: int, new: int, hours: float}
     */
    public function volunteering(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $ids = $this->volunteers()->select('id');

        return [
            'active' => $this->volunteers()->where('status', VolunteerStatus::Active->value)->count(),
            'new' => $this->volunteers()->whereBetween('created_at', [$from, $to])->count(),
            'hours' => (float) VolunteerAssignment::query()->whereIn('volunteer_id', $ids)->where('status', AssignmentStatus::Done->value)
                ->whereBetween('on_date', [$from->toDateString(), $to->toDateString()])->sum('hours'),
        ];
    }

    /**
     * Members by one dimension, largest group first (months in order).
     *
     * @param  list<string>|null  $statuses  null for every status
     * @return list<array{key: string, label: string, count: int, share: float}>
     */
    public function breakdown(string $dimension, ?array $statuses = [MemberStatus::Active->value], ?CarbonImmutable $joinedFrom = null, ?CarbonImmutable $joinedTo = null): array
    {
        $query = $this->members()
            ->when($statuses !== null, fn (Builder $q) => $q->whereIn('status', $statuses))
            ->when($joinedFrom !== null && $joinedTo !== null, fn (Builder $q) => $q->whereBetween('joined_on', [$joinedFrom?->toDateString(), $joinedTo?->toDateString()]));

        $counts = match ($dimension) {
            'woreda' => $query->toBase()->selectRaw('woreda_id as k, count(*) as c')->groupBy('woreda_id')->pluck('c', 'k')->all(),
            'sex' => $query->toBase()->selectRaw('sex as k, count(*) as c')->groupBy('sex')->pluck('c', 'k')->all(),
            'status' => $query->toBase()->selectRaw('status as k, count(*) as c')->groupBy('status')->pluck('c', 'k')->all(),
            'source' => $query->toBase()->selectRaw('source as k, count(*) as c')->groupBy('source')->pluck('c', 'k')->all(),
            'joined_month' => $query->toBase()->selectRaw("date_format(joined_on, '%Y-%m') as k, count(*) as c")->groupBy('k')->pluck('c', 'k')->all(),
            default => $this->ageBands($query),
        };

        $total = max(1, array_sum($counts));
        $rows = [];
        foreach ($counts as $key => $count) {
            $key = (string) $key;
            $rows[] = ['key' => $key, 'label' => $this->label($dimension, $key), 'count' => (int) $count, 'share' => round($count * 100 / $total, 1)];
        }
        if ($dimension === 'joined_month' || $dimension === 'age_band') {
            usort($rows, fn (array $a, array $b): int => strcmp($a['key'], $b['key']));
        } else {
            usort($rows, fn (array $a, array $b): int => $b['count'] <=> $a['count'] ?: strcmp($a['label'], $b['label']));
        }

        return $rows;
    }

    public static function dimensionLabel(string $dimension): string
    {
        return __("members.report.dimension.{$dimension}");
    }

    /** "<5" for counts a download should not show exactly. */
    public static function shown(int $count): string
    {
        return $count > 0 && $count < self::SMALL ? '<'.self::SMALL : (string) $count;
    }

    /**
     * @param  Builder<Member>  $query
     * @return array<string, int>
     */
    private function ageBands(Builder $query): array
    {
        $year = CarbonImmutable::now()->year;
        $bands = [];
        foreach ($query->toBase()->selectRaw('birth_year as y, count(*) as c')->groupBy('birth_year')->pluck('c', 'y') as $birthYear => $count) {
            // A null year of birth comes back as an empty key.
            $band = self::band((string) $birthYear === '' ? null : $year - (int) $birthYear);
            $bands[$band] = ($bands[$band] ?? 0) + (int) $count;
        }

        return $bands;
    }

    /** The age band of an age; "unknown" without a year of birth. */
    public static function band(?int $age): string
    {
        return match (true) {
            $age === null => 'unknown',
            $age < 25 => '18-24',
            $age < 35 => '25-34',
            $age < 45 => '35-44',
            $age < 55 => '45-54',
            $age < 65 => '55-64',
            default => '65+',
        };
    }

    private function label(string $dimension, string $key): string
    {
        return match ($dimension) {
            'woreda' => $key === '' ? __('members.report.no_woreda') : (Office::query()->with('translations')->find($key)?->name() ?? '—'),
            'sex' => ApplicationOptions::sexes()[$key] ?? __('members.report.unknown'),
            'status' => MemberStatus::tryFrom($key)?->label() ?? $key,
            'source' => __("members.source.{$key}"),
            'age_band' => $key === 'unknown' ? __('members.report.unknown') : $key,
            default => $key,
        };
    }
}
