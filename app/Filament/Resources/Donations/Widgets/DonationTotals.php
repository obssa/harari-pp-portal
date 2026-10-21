<?php

namespace App\Filament\Resources\Donations\Widgets;

use App\Domain\Donations\Actions\PledgeDonation;
use App\Domain\Donations\Enums\DonationStatus;
use App\Domain\Donations\Models\Donation;
use App\Settings\DonationSettings;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * This year's pledges and payments above the donations list (ADR-0060).
 */
class DonationTotals extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    /**
     * @return list<Stat>
     */
    protected function getStats(): array
    {
        $currency = app(DonationSettings::class)->currency;
        $year = fn () => Donation::query()->whereYear('created_at', (int) now()->year);
        $open = $year()->where('status', DonationStatus::Pledged->value);
        $received = Donation::query()->where('status', DonationStatus::Received->value)->whereYear('received_on', (int) now()->year);

        return [
            Stat::make(__('donations.admin.total.open'), PledgeDonation::money((string) $open->sum('amount'), $currency))
                ->description(__('donations.admin.total.count', ['count' => $year()->where('status', DonationStatus::Pledged->value)->count()])),
            Stat::make(__('donations.admin.total.received'), PledgeDonation::money((string) $received->sum('received_amount'), $currency))
                ->description(__('donations.admin.total.count', ['count' => (clone $received)->count()]))
                ->color('success'),
            Stat::make(__('donations.admin.total.all_time'), PledgeDonation::money((string) Donation::query()->where('status', DonationStatus::Received->value)->sum('received_amount'), $currency)),
        ];
    }
}
