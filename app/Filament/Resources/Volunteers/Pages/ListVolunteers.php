<?php

namespace App\Filament\Resources\Volunteers\Pages;

use App\Filament\Resources\Volunteers\VolunteerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVolunteers extends ListRecords
{
    protected static string $resource = VolunteerResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
