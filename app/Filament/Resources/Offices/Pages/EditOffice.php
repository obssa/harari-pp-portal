<?php

namespace App\Filament\Resources\Offices\Pages;

use App\Domain\Organisation\Actions\SaveOffice;
use App\Domain\Organisation\Models\Office;
use App\Filament\Resources\Offices\OfficeResource;
use App\Filament\Support\CurrentUser;
use App\Filament\Support\DirectoryFields;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditOffice extends EditRecord
{
    protected static string $resource = OfficeResource::class;

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
        /** @var Office $record */
        $record = $this->getRecord();
        $data['translations'] = DirectoryFields::fill($record, ['summary', 'address', 'hours']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Office $record */
        return app(SaveOffice::class)->handle($record, $data, CurrentUser::get());
    }
}
