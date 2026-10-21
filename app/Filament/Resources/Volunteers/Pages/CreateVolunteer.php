<?php

namespace App\Filament\Resources\Volunteers\Pages;

use App\Domain\Volunteers\Actions\SaveVolunteer;
use App\Domain\Volunteers\Models\Volunteer;
use App\Filament\Resources\Volunteers\VolunteerResource;
use App\Filament\Support\CurrentUser;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateVolunteer extends CreateRecord
{
    protected static string $resource = VolunteerResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveVolunteer::class)->handle(new Volunteer, $data, CurrentUser::get());
    }

    protected function getRedirectUrl(): string
    {
        return VolunteerResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
