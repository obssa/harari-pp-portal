<?php

namespace App\Filament\Resources\Videos\Pages;

use App\Domain\Media\Actions\SaveVideo;
use App\Domain\Media\Models\Video;
use App\Filament\Resources\Videos\VideoResource;
use App\Filament\Support\CurrentUser;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateVideo extends CreateRecord
{
    protected static string $resource = VideoResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveVideo::class)->handle(new Video, $data, CurrentUser::get());
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
