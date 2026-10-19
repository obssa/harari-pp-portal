<?php

namespace App\Filament\Resources\Galleries\Pages;

use App\Domain\Media\Actions\SaveGallery;
use App\Domain\Media\Models\Gallery;
use App\Domain\Media\Models\GalleryItem;
use App\Filament\Resources\Galleries\GalleryResource;
use App\Filament\Support\CurrentUser;
use App\Filament\Support\DirectoryFields;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditGallery extends EditRecord
{
    protected static string $resource = GalleryResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make(), RestoreAction::make()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Gallery $gallery */
        $gallery = $this->getRecord();
        $data['translations'] = DirectoryFields::fill($gallery, ['title', 'summary']);
        $data['items'] = $gallery->items()->get()
            ->map(fn (GalleryItem $item): array => ['media_id' => $item->media_id, 'caption' => $item->caption ?? []])
            ->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Gallery $record */
        return app(SaveGallery::class)->handle($record, $data, CurrentUser::get());
    }
}
