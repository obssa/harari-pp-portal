<?php

namespace App\Filament\Resources\Leaders\Pages;

use App\Filament\Resources\Leaders\LeaderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLeaders extends ListRecords
{
    protected static string $resource = LeaderResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
