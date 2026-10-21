<?php

namespace App\Filament\Resources\MediaContacts\Pages;

use App\Filament\Resources\MediaContacts\MediaContactResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMediaContacts extends ListRecords
{
    protected static string $resource = MediaContactResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
