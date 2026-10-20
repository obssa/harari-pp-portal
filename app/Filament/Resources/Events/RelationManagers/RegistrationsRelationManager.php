<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Domain\Events\Actions\CancelRegistration;
use App\Domain\Events\Actions\CheckInRegistration;
use App\Domain\Events\Enums\RegistrationStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Events\Support\RegistrationExport;
use App\Domain\Translation\Support\Locales;
use App\Filament\Support\CalendarDate;
use App\Filament\Support\CurrentUser;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Who registered for an event (ADR-0025), for staff with the
 * `events.registrations` permission: check people in, cancel a place
 * (the waiting list moves up) and download the list.
 */
class RegistrationsRelationManager extends RelationManager
{
    protected static string $relationship = 'registrations';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('events.registration.admin.title');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return CurrentUser::get()->can('events.registrations');
    }

    /** Check-in and cancelling also work from the read-only event page. */
    public function isReadOnly(): bool
    {
        return false;
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        if (! $ownerRecord instanceof Event || ! $ownerRecord->registration_enabled) {
            return null;
        }

        $count = $ownerRecord->registeredCount();

        return $ownerRecord->capacity !== null ? "{$count} / {$ownerRecord->capacity}" : (string) $count;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at')
            ->columns([
                TextColumn::make('name')->label(__('events.registration.field.name')),
                TextColumn::make('phone')->label(__('events.registration.field.phone'))->placeholder('—'),
                TextColumn::make('email')->label(__('events.registration.field.email'))->placeholder('—'),
                TextColumn::make('status')->label(__('events.registration.admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (RegistrationStatus $state): string => $state->label())
                    ->color(fn (RegistrationStatus $state): string => $state->color()),
                TextColumn::make('checked_in_at')->label(__('events.registration.admin.checked_in'))
                    ->formatStateUsing(CalendarDate::format(withTime: true))->placeholder('—'),
                TextColumn::make('locale')->label(__('admin.revisions.field.language'))
                    ->formatStateUsing(fn (string $state): string => Locales::nativeName($state))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label(__('events.registration.admin.registered_at'))
                    ->formatStateUsing(CalendarDate::format(withTime: true))->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('events.registration.admin.status'))
                    ->options(collect(RegistrationStatus::cases())->mapWithKeys(fn (RegistrationStatus $s): array => [$s->value => $s->label()])->all()),
            ])
            ->headerActions([
                Action::make('export')
                    ->label(__('events.registration.admin.export'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(fn (): StreamedResponse => $this->export()),
            ])
            ->recordActions([
                Action::make('checkIn')
                    ->label(__('events.check_in.action'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->visible(fn (EventRegistration $record): bool => $record->status === RegistrationStatus::Registered && $record->checked_in_at === null)
                    ->action(function (EventRegistration $record): void {
                        $this->authorizeRegistrations();
                        try {
                            app(CheckInRegistration::class)->handle($record, CurrentUser::get());
                            Notification::make()->success()->title(__('events.check_in.done', ['name' => $record->name]))->send();
                        } catch (ValidationException $e) {
                            Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();
                        }
                    }),
                Action::make('cancel')
                    ->label(__('events.registration.admin.cancel'))
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('events.registration.admin.cancel_help'))
                    ->visible(fn (EventRegistration $record): bool => $record->status !== RegistrationStatus::Cancelled)
                    ->action(function (EventRegistration $record): void {
                        $this->authorizeRegistrations();
                        app(CancelRegistration::class)->handle($record);
                        Notification::make()->success()->title(__('events.registration.admin.cancelled'))->send();
                    }),
            ]);
    }

    private function export(): StreamedResponse
    {
        $this->authorizeRegistrations();
        /** @var Event $event */
        $event = $this->getOwnerRecord();

        activity('events')->performedOn($event)->causedBy(CurrentUser::get())->event('exported')
            ->log('Registrations exported');

        return response()->streamDownload(
            fn () => RegistrationExport::write($event, fopen('php://output', 'wb')),
            'registrations-'.$event->starts_at->format('Y-m-d').'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    private function authorizeRegistrations(): void
    {
        abort_unless(CurrentUser::get()->can('events.registrations'), 403);
    }
}
