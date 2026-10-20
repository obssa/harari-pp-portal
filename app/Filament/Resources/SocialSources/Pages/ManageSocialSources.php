<?php

namespace App\Filament\Resources\SocialSources\Pages;

use App\Filament\Resources\SocialSources\SocialSourceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSocialSources extends ManageRecords
{
    protected static string $resource = SocialSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
