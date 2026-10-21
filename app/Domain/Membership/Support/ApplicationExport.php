<?php

namespace App\Domain\Membership\Support;

use App\Domain\Events\Support\RegistrationExport;
use App\Domain\Membership\Models\MembershipApplication;
use App\Services\Calendar\EthiopianCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The review queue as CSV for officers with `applications.export` (PLAN.md
 * §11: exports are permissioned and logged; ADR-0045). ID numbers are never
 * exported; names and phones are, because officers call applicants.
 */
final class ApplicationExport
{
    /**
     * @param  Builder<MembershipApplication>  $query
     * @param  resource  $out
     */
    public static function write(Builder $query, $out): void
    {
        $timezone = EthiopianCalendar::timezone();
        $time = fn (?CarbonImmutable $at): string => $at?->setTimezone($timezone)->format('Y-m-d H:i') ?? '';

        fwrite($out, "\u{FEFF}");
        fputcsv($out, array_map(fn (string $key): string => __("membership.export.{$key}"), [
            'reference', 'status', 'name', 'sex', 'birth_year', 'phone', 'email', 'woreda', 'kebele', 'basic_org', 'submitted_at', 'decided_at', 'language',
        ]), escape: '');

        $query->with(['woreda.translations', 'kebele.translations', 'basicOrganisation.translations'])
            ->reorder()->orderBy('submitted_at')->orderBy('id')
            ->chunk(500, function (Collection $applications) use ($out, $time): void {
                /** @var MembershipApplication $application */
                foreach ($applications as $application) {
                    fputcsv($out, array_map(RegistrationExport::cell(...), [
                        $application->reference,
                        $application->status->label(),
                        (string) $application->name,
                        ApplicationOptions::sexes()[$application->sex] ?? '',
                        (string) $application->birth_year,
                        (string) $application->phone,
                        (string) $application->email,
                        (string) $application->woreda?->name(),
                        (string) $application->kebele?->name(),
                        (string) $application->basicOrganisation?->name(),
                        $time($application->submitted_at),
                        $time($application->decided_at),
                        $application->locale,
                    ]), escape: '');
                }
            });
    }
}
