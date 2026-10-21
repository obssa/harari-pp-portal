<?php

namespace App\Filament\Resources\Donations\Pages;

use App\Filament\Resources\Donations\DonationResource;
use App\Filament\Resources\Donations\Widgets\DonationTotals;
use Filament\Resources\Pages\ListRecords;

class ListDonations extends ListRecords
{
    protected static string $resource = DonationResource::class;

    protected function getHeaderWidgets(): array
    {
        return [DonationTotals::class];
    }
}
