<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Domain\Content\Models\Category;
use App\Filament\Resources\Categories\CategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCategories extends ManageRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(fn (array $data): Category => CategoryResource::store(new Category, $data)),
        ];
    }
}
