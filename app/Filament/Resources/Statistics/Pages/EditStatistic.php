<?php

namespace App\Filament\Resources\Statistics\Pages;

use App\Domain\Projects\Actions\SaveStatistic;
use App\Domain\Projects\Models\Statistic;
use App\Domain\Projects\Models\StatisticTranslation;
use App\Filament\Resources\Statistics\StatisticResource;
use App\Filament\Support\CurrentUser;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditStatistic extends EditRecord
{
    protected static string $resource = StatisticResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Statistic $statistic */
        $statistic = $this->getRecord();
        $data['translations'] = [];
        foreach ($statistic->translations()->get() as $translation) {
            /** @var StatisticTranslation $translation */
            $data['translations'][$translation->locale] = $translation->only(['label', 'unit', 'source']);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Statistic $record */
        return app(SaveStatistic::class)->handle($record, $data, CurrentUser::get());
    }
}
