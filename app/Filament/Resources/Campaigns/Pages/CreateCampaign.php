<?php

namespace App\Filament\Resources\Campaigns\Pages;

use App\Domain\Engagement\Actions\SaveCampaign;
use App\Domain\Engagement\Models\Campaign;
use App\Filament\Resources\Campaigns\CampaignResource;
use App\Filament\Support\CurrentUser;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCampaign extends CreateRecord
{
    protected static string $resource = CampaignResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveCampaign::class)->handle(new Campaign, $data, CurrentUser::get());
    }

    protected function getRedirectUrl(): string
    {
        return CampaignResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
