<?php

namespace App\Filament\Resources\Tags\Pages;

use App\Domain\Content\Models\Tag;
use App\Filament\Resources\Tags\TagResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTags extends ManageRecords
{
    protected static string $resource = TagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(fn (array $data): Tag => TagResource::store(new Tag, $data)),
        ];
    }
}
