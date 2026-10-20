<?php

namespace App\Domain\Analytics\Support;

use App\Services\Calendar\EthiopianCalendar;
use Carbon\CarbonImmutable;

/**
 * The periods offered by the report builder (ADR-0040), as days in Addis
 * Ababa time.
 */
final class ReportPeriod
{
    public const KEYS = ['7', '30', '90', '365', 'month', 'last_month'];

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function range(string $key, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now(EthiopianCalendar::timezone());

        return match ($key) {
            '7', '90', '365' => [$now->subDays((int) $key - 1)->startOfDay(), $now],
            'month' => [$now->startOfMonth(), $now],
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            default => [$now->subDays(29)->startOfDay(), $now],
        };
    }

    public static function label(string $key): string
    {
        return match ($key) {
            '7' => __('analytics.period.7'),
            '90' => __('analytics.period.90'),
            '365' => __('analytics.period.365'),
            'month' => __('analytics.period.month'),
            'last_month' => __('analytics.period.last_month'),
            default => __('analytics.period.30'),
        };
    }

    /**
     * @return array<int|string, string> numeric keys such as "7" become integers in PHP arrays
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::KEYS as $key) {
            $options[$key] = self::label($key);
        }

        return $options;
    }
}
