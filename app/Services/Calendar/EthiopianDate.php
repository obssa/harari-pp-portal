<?php

namespace App\Services\Calendar;

/**
 * A day in the Ethiopian calendar (13 months; Pagume has 5 or 6 days).
 */
final readonly class EthiopianDate
{
    public function __construct(
        public int $year,
        public int $month,
        public int $day,
    ) {}

    public function monthName(?string $locale = null): string
    {
        return __("calendar.ethiopian.month.{$this->month}", [], $locale);
    }

    /**
     * @return array{year: int, month: int, day: int}
     */
    public function toArray(): array
    {
        return ['year' => $this->year, 'month' => $this->month, 'day' => $this->day];
    }
}
