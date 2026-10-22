<?php

namespace App\Filament\Resources\Menus\Pages;

use App\Domain\Content\Models\Menu;
use App\Filament\Resources\Menus\MenuResource;
use App\Filament\Support\ViewOnSite;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageMenus extends ManageRecords
{
    protected static string $resource = MenuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewOnSite::site(),
            CreateAction::make()
                ->visible(fn (): bool => Menu::query()->count() < count(Menu::LOCATIONS))
                ->mutateDataUsing(fn (array $data): array => [...$data, 'updated_by' => auth()->id()]),
        ];
    }
}
