<?php

namespace App\Domain\Forms\Support;

use App\Services\Calendar\EthiopianCalendar;
use Carbon\CarbonImmutable;

/**
 * Answer-by dates in working days, Monday to Friday, ending at the close of
 * the day in Addis Ababa. Public holidays are not counted out yet.
 */
final class WorkingDays
{
    public static function after(CarbonImmutable $from, int $days): CarbonImmutable
    {
        $date = $from->setTimezone(EthiopianCalendar::timezone());
        $left = max(1, $days);
        while ($left > 0) {
            $date = $date->addDay();
            if (! $date->isWeekend()) {
                $left--;
            }
        }

        return $date->endOfDay()->utc();
    }
}
