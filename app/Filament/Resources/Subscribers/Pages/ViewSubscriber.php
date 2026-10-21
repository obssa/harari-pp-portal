<?php

namespace App\Filament\Resources\Subscribers\Pages;

use App\Domain\Engagement\Actions\Subscribe;
use App\Domain\Engagement\Models\Subscriber;
use App\Filament\Resources\Subscribers\SubscriberResource;
use App\Filament\Support\CurrentUser;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewSubscriber extends ViewRecord
{
    protected static string $resource = SubscriberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('unsubscribe')
                ->label(__('newsletter.unsubscribe'))
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('danger')
                ->visible(fn (): bool => CurrentUser::get()->can('subscribers.manage') && $this->subscriber()->unsubscribed_at === null)
                ->requiresConfirmation()
                ->modalDescription(__('newsletter.admin.unsubscribe_help'))
                ->action(function (): void {
                    abort_unless(CurrentUser::get()->can('subscribers.manage'), 403);
                    app(Subscribe::class)->unsubscribe($this->subscriber());
                    activity('newsletter')->performedOn($this->subscriber())->causedBy(CurrentUser::get())->event('unsubscribed_by_staff')->log('Subscriber unsubscribed by staff');
                    Notification::make()->success()->title(__('newsletter.unsubscribed'))->send();
                    $this->refreshFormData(['unsubscribed_at']);
                }),
        ];
    }

    private function subscriber(): Subscriber
    {
        /** @var Subscriber $subscriber */
        $subscriber = $this->getRecord();

        return $subscriber;
    }
}
