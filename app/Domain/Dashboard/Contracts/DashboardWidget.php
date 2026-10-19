<?php

namespace App\Domain\Dashboard\Contracts;

/**
 * A widget the dashboard can show (PLAN.md §7 Dashboard: widget registry
 * with permission, default size and data; ADR-0021).
 */
interface DashboardWidget
{
    /** Stable key stored in saved layouts. */
    public static function key(): string;

    public static function title(): string;

    /** Permission needed to see the widget, or null for every staff member. */
    public static function permission(): ?string;

    /** Width in columns of the 12-column grid. */
    public static function defaultSize(): int;
}
