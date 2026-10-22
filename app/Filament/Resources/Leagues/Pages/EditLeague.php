<?php

namespace App\Filament\Resources\Leagues\Pages;

use App\Domain\Organisation\Actions\SaveLeague;
use App\Domain\Organisation\Models\League;
use App\Filament\Resources\Leagues\LeagueResource;
use App\Filament\Support\CurrentUser;
use App\Filament\Support\DirectoryFields;
use App\Filament\Support\ViewOnSite;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditLeague extends EditRecord
{
    protected static string $resource = LeagueResource::class;

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
        /** @var League $record */
        $record = $this->getRecord();
        $data['translations'] = DirectoryFields::fill($record, ['summary']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var League $record */
        return app(SaveLeague::class)->handle($record, $data, CurrentUser::get());
    }
}
