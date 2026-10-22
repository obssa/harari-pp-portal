<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Domain\Projects\Actions\SaveProject;
use App\Domain\Projects\Models\Project;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Support\CurrentUser;
use App\Filament\Support\DirectoryFields;
use App\Filament\Support\ViewOnSite;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

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
        /** @var Project $project */
        $project = $this->getRecord();
        $data['translations'] = DirectoryFields::fill($project, ['title', 'summary', 'location']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Project $record */
        return app(SaveProject::class)->handle($record, $data, CurrentUser::get());
    }
}
