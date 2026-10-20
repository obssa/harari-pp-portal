<?php

namespace App\Filament\Resources\Statistics\Pages;

use App\Domain\Projects\Actions\SaveStatistic;
use App\Domain\Projects\Models\Statistic;
use App\Filament\Resources\Statistics\StatisticResource;
use App\Filament\Support\CurrentUser;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStatistic extends CreateRecord
{
    protected static string $resource = StatisticResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveStatistic::class)->handle(new Statistic, $data, CurrentUser::get());
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
