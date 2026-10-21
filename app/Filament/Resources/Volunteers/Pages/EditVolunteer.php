<?php

namespace App\Filament\Resources\Volunteers\Pages;

use App\Domain\Volunteers\Actions\SaveVolunteer;
use App\Domain\Volunteers\Models\Volunteer;
use App\Filament\Resources\Volunteers\VolunteerResource;
use App\Filament\Support\CurrentUser;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditVolunteer extends EditRecord
{
    protected static string $resource = VolunteerResource::class;

    /**
     * Contact details are hidden from the model's array form; the form gets them here.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Volunteer $volunteer */
        $volunteer = $this->getRecord();

        return [...$data, 'name' => $volunteer->name, 'phone' => $volunteer->phone, 'email' => $volunteer->email];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Volunteer $record */
        return app(SaveVolunteer::class)->handle($record, $data, CurrentUser::get());
    }

    protected function getRedirectUrl(): string
    {
        return VolunteerResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
