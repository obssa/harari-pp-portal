<?php

namespace App\Filament\Resources\Donations\Pages;

use App\Domain\Donations\Actions\RecordDonation;
use App\Domain\Donations\Enums\DonationStatus;
use App\Domain\Donations\Models\Donation;
use App\Filament\Resources\Donations\DonationResource;
use App\Filament\Support\CurrentUser;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewDonation extends ViewRecord
{
    protected static string $resource = DonationResource::class;

    protected function getHeaderActions(): array
    {
        $pledged = fn (): bool => $this->donation()->status === DonationStatus::Pledged && CurrentUser::get()->can('update', $this->donation());

        return [
            Action::make('receive')
                ->label(__('donations.admin.receive'))
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->visible($pledged)
                ->modalDescription(__('donations.admin.receive_help'))
                ->fillForm(fn (): array => ['received_amount' => $this->donation()->amount, 'received_on' => now()->toDateString()])
                ->schema([
                    TextInput::make('received_amount')->label(__('donations.field.received_amount'))->numeric()->minValue(0.01)->required(),
                    DatePicker::make('received_on')->label(__('donations.receipt.received_on'))->maxDate(now())->required(),
                    TextInput::make('payment_reference')->label(__('donations.field.payment_reference'))->helperText(__('donations.admin.payment_reference_help'))->maxLength(100),
                ])
                ->action(function (array $data): void {
                    $donation = app(RecordDonation::class)->receive($this->donation(), (string) $data['received_amount'], (string) $data['received_on'], $data['payment_reference'] ?? null, CurrentUser::get());
                    Notification::make()->success()->title(__('donations.admin.received', ['receipt' => $donation->receipt_no]))->send();
                    $this->refreshFormData(['status', 'received_amount', 'received_on', 'payment_reference', 'receipt_no']);
                }),
            Action::make('receipt')
                ->label(__('donations.admin.print_receipt'))
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->visible(fn (): bool => $this->donation()->receipt_no !== null)
                ->url(fn (): string => route('donations.receipt', ['donation' => $this->donation()]), shouldOpenInNewTab: true),
            Action::make('cancel')
                ->label(__('donations.admin.cancel'))
                ->icon(Heroicon::OutlinedXCircle)
                ->color('gray')
                ->visible($pledged)
                ->schema([Textarea::make('reason')->label(__('membership.admin.reason'))->required()->rows(3)->maxLength(500)])
                ->action(function (array $data): void {
                    app(RecordDonation::class)->cancel($this->donation(), (string) $data['reason'], CurrentUser::get());
                    Notification::make()->success()->title(__('donations.admin.cancelled'))->send();
                    $this->refreshFormData(['status', 'cancel_reason']);
                }),
        ];
    }

    private function donation(): Donation
    {
        /** @var Donation $donation */
        $donation = $this->getRecord();

        return $donation;
    }
}
