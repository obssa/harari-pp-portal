<?php

namespace App\Filament\Resources\MediaContacts\Pages;

use App\Domain\Press\Actions\SaveMediaContact;
use App\Domain\Press\Models\MediaContact;
use App\Filament\Resources\MediaContacts\MediaContactResource;
use App\Filament\Support\CurrentUser;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMediaContact extends CreateRecord
{
    protected static string $resource = MediaContactResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveMediaContact::class)->handle(new MediaContact, $data, CurrentUser::get());
    }

    protected function getRedirectUrl(): string
    {
        return MediaContactResource::getUrl('index');
    }
}
