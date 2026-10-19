<?php

namespace App\Services\Calendar;

use Andegna\DateTimeFactory;
use App\Settings\SiteSettings;
use Carbon\CarbonImmutable;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use Throwable;

/**
 * Ethiopian and Gregorian dates for display (PLAN.md §6: Ethiopian calendar
 * everywhere a date is shown, Gregorian alongside, order set in the admin).
 *
 * Dates are stored in UTC and converted to Addis Ababa time first, so an
 * event at 22:30 UTC on 10 September lands on Meskerem 1, not the day before.
 * Times use the 24-hour clock in Addis Ababa time.
 */
final class EthiopianCalendar
{
    public const ETHIOPIAN = 'ethiopian';

    public const GREGORIAN = 'gregorian';

    public static function timezone(): string
    {
        return (string) config('portal.timezone', 'Africa/Addis_Ababa');
    }

    public function toEthiopian(DateTimeInterface $date): EthiopianDate
    {
        $local = $this->local($date);
        $ethiopian = DateTimeFactory::fromDateTime(
            new DateTime($local->format('Y-m-d H:i:s'), new DateTimeZone(self::timezone())),
        );

        return new EthiopianDate($ethiopian->getYear(), $ethiopian->getMonth(), $ethiopian->getDay());
    }

    /**
     * Midnight (Addis Ababa) of an Ethiopian date, as a Gregorian date.
     */
    public function toGregorian(int $year, int $month, int $day): CarbonImmutable
    {
        try {
            $ethiopian = DateTimeFactory::of($year, $month, $day, 0, 0, 0, new DateTimeZone(self::timezone()));
        } catch (Throwable $e) {
            throw new InvalidArgumentException("{$year}-{$month}-{$day} is not a valid Ethiopian date.", 0, $e);
        }

        return CarbonImmutable::instance($ethiopian->toGregorian());
    }

    public function formatEthiopian(DateTimeInterface $date, ?string $locale = null): string
    {
        $ethiopian = $this->toEthiopian($date);

        return __('calendar.ethiopian.date', [
            'day' => $ethiopian->day,
            'month' => $ethiopian->monthName($locale),
            'year' => $ethiopian->year,
        ], $locale);
    }

    public function formatGregorian(DateTimeInterface $date, ?string $locale = null): string
    {
        $local = $this->local($date);

        return __('calendar.gregorian.date', [
            'day' => $local->day,
            'month' => __("calendar.gregorian.month.{$local->month}", [], $locale),
            'year' => $local->year,
        ], $locale);
    }

    /**
     * The date as the site shows it: the leading calendar, with the other in
     * parentheses unless an admin turned that off. $only forces one calendar.
     */
    public function format(DateTimeInterface $date, ?string $locale = null, bool $withTime = false, ?string $only = null): string
    {
        [$primary, $showSecondary] = $this->preferences();
        $secondary = $primary === self::ETHIOPIAN ? self::GREGORIAN : self::ETHIOPIAN;

        $text = match (true) {
            $only !== null => $this->formatIn($only, $date, $locale),
            $showSecondary => __('calendar.dual', [
                'primary' => $this->formatIn($primary, $date, $locale),
                'secondary' => $this->formatIn($secondary, $date, $locale),
            ], $locale),
            default => $this->formatIn($primary, $date, $locale),
        };

        if (! $withTime) {
            return $text;
        }

        return __('calendar.datetime', ['date' => $text, 'time' => $this->local($date)->format('H:i')], $locale);
    }

    /**
     * Ethiopian and Gregorian year for footers, e.g. "2019 (2026)".
     */
    public function years(DateTimeInterface $date): string
    {
        return $this->toEthiopian($date)->year.' ('.$this->local($date)->year.')';
    }

    private function formatIn(string $calendar, DateTimeInterface $date, ?string $locale): string
    {
        return match ($calendar) {
            self::ETHIOPIAN => $this->formatEthiopian($date, $locale),
            self::GREGORIAN => $this->formatGregorian($date, $locale),
            default => throw new InvalidArgumentException("Unknown calendar: {$calendar}"),
        };
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private function preferences(): array
    {
        try {
            $settings = app(SiteSettings::class);
            $primary = in_array($settings->calendar_primary, [self::ETHIOPIAN, self::GREGORIAN], true)
                ? $settings->calendar_primary
                : self::ETHIOPIAN;

            return [$primary, $settings->show_secondary_calendar];
        } catch (Throwable) {
            return [self::ETHIOPIAN, true];
        }
    }

    private function local(DateTimeInterface $date): CarbonImmutable
    {
        return CarbonImmutable::instance($date)->setTimezone(self::timezone());
    }
}
