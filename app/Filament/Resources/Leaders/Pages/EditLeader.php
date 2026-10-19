<?php

namespace App\Filament\Resources\Leaders\Pages;

use App\Domain\Organisation\Actions\SaveLeader;
use App\Domain\Organisation\Models\Leader;
use App\Filament\Resources\Leaders\LeaderResource;
use App\Filament\Support\CurrentUser;
use App\Filament\Support\DirectoryFields;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditLeader extends EditRecord
{
    protected static string $resource = LeaderResource::class;

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
        /** @var Leader $record */
        $record = $this->getRecord();
        $data['translations'] = DirectoryFields::fill($record, ['position', 'summary']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Leader $record */
        return app(SaveLeader::class)->handle($record, $data, CurrentUser::get());
    }
}
