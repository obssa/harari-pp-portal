<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Domain\Projects\Actions\SaveProject;
use App\Domain\Projects\Models\Project;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Support\CurrentUser;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProject extends CreateRecord
{
    protected static string $resource = ProjectResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveProject::class)->handle(new Project, $data, CurrentUser::get());
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
