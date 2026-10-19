<?php

namespace App\Filament\Support;

use App\Services\Calendar\EthiopianCalendar;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;

/**
 * Dual-calendar dates for admin tables and detail views (PLAN.md §7:
 * dual calendar on all date widgets):
 *
 *     TextColumn::make('created_at')->formatStateUsing(CalendarDate::format(withTime: true))
 */
final class CalendarDate
{
    /**
     * @return Closure(mixed): ?string
     */
    public static function format(bool $withTime = false): Closure
    {
        return function (mixed $state) use ($withTime): ?string {
            if ($state === null || $state === '') {
                return null;
            }

            $date = $state instanceof DateTimeInterface ? $state : CarbonImmutable::parse((string) $state);

            return app(EthiopianCalendar::class)->format($date, withTime: $withTime);
        };
    }
}
