<?php

namespace App\Domain\Membership\Support;

use App\Domain\Events\Support\RegistrationExport;
use App\Domain\Membership\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The members on screen as CSV for staff with `members.export` (PLAN.md §8,
 * §11; ADR-0046). ID numbers are never exported.
 */
final class MemberExport
{
    /**
     * @param  Builder<Member>  $query
     * @param  resource  $out
     */
    public static function write(Builder $query, $out): void
    {
        $date = fn (?CarbonImmutable $on): string => $on?->toDateString() ?? '';

        fwrite($out, "\u{FEFF}");
        fputcsv($out, array_map(fn (string $key): string => __("members.export.{$key}"), [
            'member_no', 'status', 'name', 'sex', 'birth_year', 'phone', 'email', 'woreda', 'kebele', 'basic_org', 'joined_on', 'renewed_on', 'renewal_due_on', 'source',
        ]), escape: '');

        $query->with(['woreda.translations', 'kebele.translations', 'basicOrganisation.translations'])
            ->reorder()->orderBy('member_no')
            ->chunk(500, function (Collection $members) use ($out, $date): void {
                /** @var Member $member */
                foreach ($members as $member) {
                    fputcsv($out, array_map(RegistrationExport::cell(...), [
                        $member->member_no,
                        $member->status->label(),
                        $member->name,
                        ApplicationOptions::sexes()[$member->sex] ?? '',
                        (string) $member->birth_year,
                        (string) $member->phone,
                        (string) $member->email,
                        (string) $member->woreda?->name(),
                        (string) $member->kebele?->name(),
                        (string) $member->basicOrganisation?->name(),
                        $date($member->joined_on),
                        $date($member->renewed_on),
                        $date($member->renewal_due_on),
                        __("members.source.{$member->source}"),
                    ]), escape: '');
                }
            });
    }
}
