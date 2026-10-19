<?php

namespace App\Filament\Resources\Galleries\Pages;

use App\Domain\Media\Actions\SaveGallery;
use App\Domain\Media\Models\Gallery;
use App\Filament\Resources\Galleries\GalleryResource;
use App\Filament\Support\CurrentUser;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateGallery extends CreateRecord
{
    protected static string $resource = GalleryResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveGallery::class)->handle(new Gallery, $data, CurrentUser::get());
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
