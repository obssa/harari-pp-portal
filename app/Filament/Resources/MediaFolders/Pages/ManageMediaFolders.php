<?php

namespace App\Filament\Resources\MediaFolders\Pages;

use App\Filament\Resources\MediaFolders\MediaFolderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageMediaFolders extends ManageRecords
{
    protected static string $resource = MediaFolderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
