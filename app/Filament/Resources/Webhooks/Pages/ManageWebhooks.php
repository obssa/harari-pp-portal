<?php

namespace App\Filament\Resources\Webhooks\Pages;

use App\Domain\Webhooks\Models\Webhook;
use App\Filament\Resources\Webhooks\WebhookResource;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Str;

class ManageWebhooks extends ManageRecords
{
    protected static string $resource = WebhookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateDataUsing(fn (array $data): array => [...$data, 'secret' => Str::random(48)])
                ->after(function (Webhook $record): void {
                    // The secret is shown once; the receiver uses it to check signatures.
                    Notification::make()
                        ->success()
                        ->persistent()
                        ->title(__('admin.webhooks.secret_title'))
                        ->body(__('admin.webhooks.secret_body', ['secret' => $record->secret]))
                        ->send();
                }),
        ];
    }
}
