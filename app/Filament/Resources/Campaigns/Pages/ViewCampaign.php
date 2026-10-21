<?php

namespace App\Filament\Resources\Campaigns\Pages;

use App\Domain\Engagement\Actions\SendCampaign;
use App\Domain\Engagement\Enums\CampaignStatus;
use App\Domain\Engagement\Models\Campaign;
use App\Domain\Engagement\Support\Audience;
use App\Domain\Translation\Support\Locales;
use App\Filament\Resources\Campaigns\CampaignResource;
use App\Filament\Support\CurrentUser;
use App\Services\Calendar\EthiopianCalendar;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewCampaign extends ViewRecord
{
    protected static string $resource = CampaignResource::class;

    protected function getHeaderActions(): array
    {
        $open = fn (): bool => in_array($this->campaign()->status, [CampaignStatus::Draft, CampaignStatus::Scheduled], true);
        $canSend = fn (): bool => CurrentUser::get()->can('campaigns.send');

        return [
            EditAction::make(),
            Action::make('test')
                ->label(__('campaigns.admin.test'))
                ->icon(Heroicon::OutlinedBeaker)
                ->color('gray')
                ->visible(fn (): bool => $this->campaign()->languages() !== [])
                ->modalDescription(fn (): string => $this->campaign()->channel === Campaign::EMAIL
                    ? __('campaigns.admin.test_email', ['email' => CurrentUser::get()->email])
                    : __('campaigns.admin.test_sms'))
                ->schema(fn (): array => [
                    Select::make('locale')->label(__('newsletter.field.language'))->required()->default($this->campaign()->languages()[0] ?? 'en')
                        ->options(collect($this->campaign()->languages())->mapWithKeys(fn (string $l): array => [$l => Locales::nativeName($l)])->all()),
                    ...($this->campaign()->channel === Campaign::SMS ? [TextInput::make('phone')->label(__('newsletter.field.phone'))->tel()->required()] : []),
                ])
                ->action(function (array $data): void {
                    app(SendCampaign::class)->test($this->campaign(), CurrentUser::get(), $data['phone'] ?? null, (string) $data['locale']);
                    Notification::make()->success()->title(__('campaigns.admin.tested'))->send();
                }),
            Action::make('send')
                ->label(__('campaigns.admin.send'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('success')
                ->visible(fn (): bool => $open() && $canSend())
                ->requiresConfirmation()
                ->modalHeading(__('campaigns.admin.send_heading'))
                ->modalDescription(fn (): string => __('campaigns.admin.send_help', ['count' => (new Audience(CurrentUser::get()))->count($this->campaign())]))
                ->action(function (): void {
                    $count = app(SendCampaign::class)->start($this->campaign(), CurrentUser::get());
                    Notification::make()->success()->title(__('campaigns.admin.sending', ['count' => $count]))->send();
                    $this->refreshFormData(['status', 'started_at']);
                }),
            Action::make('schedule')
                ->label(__('campaigns.admin.schedule'))
                ->icon(Heroicon::OutlinedClock)
                ->color('gray')
                ->visible(fn (): bool => $open() && $canSend())
                ->schema([
                    DateTimePicker::make('scheduled_at')->label(__('campaigns.field.scheduled_at'))->required()->seconds(false)
                        ->timezone(EthiopianCalendar::timezone())->minDate(now()),
                ])
                ->action(function (array $data): void {
                    $at = CarbonImmutable::parse((string) $data['scheduled_at']);
                    app(SendCampaign::class)->schedule($this->campaign(), $at, CurrentUser::get());
                    Notification::make()->success()->title(__('campaigns.admin.scheduled', ['date' => app(EthiopianCalendar::class)->format($at, null, true)]))->send();
                    $this->refreshFormData(['status', 'scheduled_at']);
                }),
            Action::make('cancel')
                ->label(fn (): string => $this->campaign()->status === CampaignStatus::Scheduled ? __('campaigns.admin.unschedule') : __('campaigns.admin.stop'))
                ->icon(Heroicon::OutlinedStop)
                ->color('danger')
                ->visible(fn (): bool => $canSend() && in_array($this->campaign()->status, [CampaignStatus::Scheduled, CampaignStatus::Sending], true))
                ->requiresConfirmation()
                ->action(function (): void {
                    app(SendCampaign::class)->cancel($this->campaign(), CurrentUser::get());
                    Notification::make()->success()->title(__('campaigns.admin.cancelled'))->send();
                    $this->refreshFormData(['status', 'scheduled_at', 'finished_at']);
                }),
        ];
    }

    private function campaign(): Campaign
    {
        /** @var Campaign $campaign */
        $campaign = $this->getRecord();

        return $campaign;
    }
}
