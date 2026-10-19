<?php

namespace App\Domain\Dashboard\Support;

use App\Domain\Translation\Support\Locales;
use Carbon\CarbonImmutable;

/**
 * The dashboard's global controls (PLAN.md §7: range, language, office),
 * read by every widget from the page filters.
 */
final readonly class DashboardFilters
{
    /** Range choices in days; "efy" is the Ethiopian fiscal year (from 1 Hamle, 8 July). */
    public const RANGES = ['7', '30', '90', '365', 'efy'];

    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public ?string $locale,
        public ?string $officeId,
    ) {}

    /**
     * @param  array<string, mixed>|null  $filters
     */
    public static function from(?array $filters): self
    {
        $range = (string) ($filters['range'] ?? '30');
        $to = CarbonImmutable::now();
        $from = match ($range) {
            '7' => $to->subDays(7),
            '90' => $to->subDays(90),
            '365' => $to->subDays(365),
            'efy' => self::fiscalYearStart($to),
            default => $to->subDays(30),
        };

        $locale = (string) ($filters['locale'] ?? '');
        $office = (string) ($filters['office'] ?? '');

        return new self(
            $from->startOfDay(),
            $to,
            in_array($locale, Locales::all(), true) ? $locale : null,
            $office !== '' ? $office : null,
        );
    }

    /** The same length of time just before this range, for comparisons. */
    public function previousFrom(): CarbonImmutable
    {
        return $this->from->subSeconds((int) $this->from->diffInSeconds($this->to));
    }

    /**
     * Ethiopia's fiscal year starts on 1 Hamle (8 July in the Gregorian calendar).
     */
    public static function fiscalYearStart(CarbonImmutable $date): CarbonImmutable
    {
        $start = $date->setDate($date->year, 7, 8)->startOfDay();

        return $date->lessThan($start) ? $start->subYear() : $start;
    }
}
