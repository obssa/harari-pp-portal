<?php

namespace App\Filament\Resources\AnalyticsReports\Pages;

use App\Filament\Resources\AnalyticsReports\AnalyticsReportResource;
use App\Filament\Support\CurrentUser;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAnalyticsReports extends ManageRecords
{
    protected static string $resource = AnalyticsReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->mutateDataUsing(fn (array $data): array => [...$data, 'created_by' => CurrentUser::get()->id]),
        ];
    }
}
