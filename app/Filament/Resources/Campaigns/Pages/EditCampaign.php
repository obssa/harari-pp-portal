<?php

namespace App\Filament\Resources\Campaigns\Pages;

use App\Domain\Engagement\Actions\SaveCampaign;
use App\Domain\Engagement\Models\Campaign;
use App\Filament\Resources\Campaigns\CampaignResource;
use App\Filament\Support\CurrentUser;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCampaign extends EditRecord
{
    protected static string $resource = CampaignResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Campaign $record */
        return app(SaveCampaign::class)->handle($record, $data, CurrentUser::get());
    }

    protected function getRedirectUrl(): string
    {
        return CampaignResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
