<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * The donations module (PLAN.md §13 Phase 8, ADR-0060): off by default,
 * switched on by a Super Admin only once Ethiopian political-finance rules
 * and the party allow it.
 */
class DonationSettings extends Settings
{
    public bool $enabled;

    /** @var array<string, string> per language: why and how to give */
    public array $intro;

    /** @var array<string, string> per language: what to write on the transfer, what happens next */
    public array $instructions;

    /**
     * The party's accounts for transfers. The settings package reads `@var`
     * and cannot cast nested arrays, so the type is given to PHPStan only.
     *
     * @phpstan-var list<array{bank: string, name: string, number: string}>
     */
    public array $accounts;

    /** The smallest pledge accepted, in the currency below. */
    public int $minimum;

    public string $currency;

    public static function group(): string
    {
        return 'donations';
    }
}
