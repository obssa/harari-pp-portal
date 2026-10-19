<?php

namespace App\Filament\Resources\Leagues\Pages;

use App\Domain\Organisation\Actions\SaveLeague;
use App\Domain\Organisation\Models\League;
use App\Filament\Resources\Leagues\LeagueResource;
use App\Filament\Support\CurrentUser;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateLeague extends CreateRecord
{
    protected static string $resource = LeagueResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveLeague::class)->handle(new League, $data, CurrentUser::get());
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
