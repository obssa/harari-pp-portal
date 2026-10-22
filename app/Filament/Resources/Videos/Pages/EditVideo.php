<?php

namespace App\Filament\Resources\Videos\Pages;

use App\Domain\Media\Actions\SaveVideo;
use App\Domain\Media\Models\Video;
use App\Filament\Resources\Videos\VideoResource;
use App\Filament\Support\CurrentUser;
use App\Filament\Support\DirectoryFields;
use App\Filament\Support\ViewOnSite;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditVideo extends EditRecord
{
    protected static string $resource = VideoResource::class;

    protected function getHeaderActions(): array
    {
        return [...ViewOnSite::header(), DeleteAction::make(), RestoreAction::make()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Video $video */
        $video = $this->getRecord();
        $data['translations'] = DirectoryFields::fill($video, ['title', 'summary']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Video $record */
        return app(SaveVideo::class)->handle($record, $data, CurrentUser::get());
    }
}
