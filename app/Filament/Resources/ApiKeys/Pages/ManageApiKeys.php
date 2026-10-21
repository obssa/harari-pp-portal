<?php

namespace App\Filament\Resources\ApiKeys\Pages;

use App\Domain\Api\Actions\IssueApiKey;
use App\Domain\Api\Models\ApiKey;
use App\Filament\Resources\ApiKeys\ApiKeyResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Auth;

class ManageApiKeys extends ManageRecords
{
    protected static string $resource = ApiKeyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openapi')
                ->label(__('admin.api_keys.action.description'))
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->url(route('api.v1.openapi'), shouldOpenInNewTab: true),
            CreateAction::make()
                ->label(__('admin.api_keys.action.issue'))
                ->createAnother(false)
                ->using(function (array $data): ApiKey {
                    $actor = Auth::user();
                    abort_unless($actor instanceof User, 403);
                    [$key, $plain] = app(IssueApiKey::class)->handle($data, $actor);

                    // Shown once: the app's developer copies it now; only its hash is kept.
                    Notification::make()
                        ->success()
                        ->persistent()
                        ->title(__('admin.api_keys.key_title'))
                        ->body(__('admin.api_keys.key_body', ['key' => $plain]))
                        ->send();

                    return $key;
                }),
        ];
    }
}
