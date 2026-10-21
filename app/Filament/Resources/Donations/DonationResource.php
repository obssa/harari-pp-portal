<?php

namespace App\Filament\Resources\Donations;

use App\Domain\Donations\Actions\PledgeDonation;
use App\Domain\Donations\Enums\DonationStatus;
use App\Domain\Donations\Models\Donation;
use App\Domain\Membership\Support\Mask;
use App\Domain\Translation\Support\Locales;
use App\Filament\Resources\Donations\Pages\ListDonations;
use App\Filament\Resources\Donations\Pages\ViewDonation;
use App\Filament\Support\CalendarDate;
use App\Support\PhoneNumber;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Donations (ADR-0060): pledges from the donate page and the payments
 * recorded against them, with receipts and totals. Names are shortened in
 * the list; the full details are on each pledge's page.
 */
class DonationResource extends Resource
{
    protected static ?string $model = Donation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function getModelLabel(): string
    {
        return __('donations.admin.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('donations.admin.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.communication');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference')->label(__('donations.reference'))->fontFamily('mono')->searchable(),
                TextColumn::make('donor')->label(__('donations.field.name'))
                    ->state(fn (Donation $record): string => $record->erased_at !== null ? '—' : Mask::name($record->name)),
                TextColumn::make('amount')->label(__('donations.field.amount'))
                    ->state(fn (Donation $record): string => PledgeDonation::money($record->amount, $record->currency))->alignEnd(),
                TextColumn::make('status')->label(__('membership.admin.status'))->badge()
                    ->formatStateUsing(fn (DonationStatus $state): string => $state->label())
                    ->color(fn (DonationStatus $state): string => $state->color()),
                TextColumn::make('received_amount')->label(__('donations.field.received_amount'))
                    ->state(fn (Donation $record): ?string => $record->received_amount !== null ? PledgeDonation::money($record->received_amount, $record->currency) : null)
                    ->placeholder('—')->alignEnd(),
                TextColumn::make('receipt_no')->label(__('donations.field.receipt_no'))->fontFamily('mono')->placeholder('—')->searchable(),
                TextColumn::make('created_at')->label(__('donations.field.pledged_on'))->formatStateUsing(CalendarDate::format())->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('membership.admin.status'))->options(DonationStatus::options()),
                SelectFilter::make('year')->label(__('donations.admin.year'))
                    ->options(fn (): array => collect(range((int) now()->year, (int) now()->year - 5))->mapWithKeys(fn (int $y): array => [$y => (string) $y])->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null) ? $query->whereYear('created_at', (int) $data['value']) : $query),
            ])
            ->recordActions([ViewAction::make()])
            ->recordUrl(fn (Donation $record): string => self::getUrl('view', ['record' => $record]))
            ->emptyStateHeading(__('donations.admin.empty'));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make(__('donations.admin.donor'))->schema([
                TextEntry::make('name')->label(__('donations.field.name'))->state(fn (Donation $record): string => $record->displayName()),
                TextEntry::make('phone')->label(__('donations.field.phone'))->state(fn (Donation $record): ?string => $record->phone !== null ? PhoneNumber::mask($record->phone) : null)->placeholder('—'),
                TextEntry::make('email')->label(__('donations.field.email'))->placeholder('—'),
                TextEntry::make('anonymous')->label(__('donations.field.anonymous'))
                    ->state(fn (Donation $record): string => $record->anonymous ? __('donations.admin.yes') : __('donations.admin.no')),
                TextEntry::make('locale')->label(__('newsletter.field.language'))->formatStateUsing(fn (string $state): string => Locales::nativeName($state)),
            ]),
            Section::make(__('donations.admin.pledge'))->schema([
                TextEntry::make('reference')->label(__('donations.reference'))->fontFamily('mono')->copyable(),
                TextEntry::make('amount')->label(__('donations.field.amount'))->state(fn (Donation $record): string => PledgeDonation::money($record->amount, $record->currency)),
                TextEntry::make('purpose')->label(__('donations.field.purpose'))->placeholder('—'),
                TextEntry::make('method')->label(__('donations.receipt.method'))->formatStateUsing(fn (string $state): string => __('donations.method.'.$state)),
                TextEntry::make('created_at')->label(__('donations.field.pledged_on'))->formatStateUsing(CalendarDate::format(withTime: true)),
            ]),
            Section::make(__('donations.admin.payment'))->columnSpanFull()->columns(3)->schema([
                TextEntry::make('status')->label(__('membership.admin.status'))->badge()
                    ->formatStateUsing(fn (DonationStatus $state): string => $state->label())
                    ->color(fn (DonationStatus $state): string => $state->color()),
                TextEntry::make('received_amount')->label(__('donations.field.received_amount'))
                    ->state(fn (Donation $record): ?string => $record->received_amount !== null ? PledgeDonation::money($record->received_amount, $record->currency) : null)->placeholder('—'),
                TextEntry::make('received_on')->label(__('donations.receipt.received_on'))->formatStateUsing(CalendarDate::format())->placeholder('—'),
                TextEntry::make('payment_reference')->label(__('donations.field.payment_reference'))->placeholder('—'),
                TextEntry::make('receipt_no')->label(__('donations.field.receipt_no'))->fontFamily('mono')->placeholder('—'),
                TextEntry::make('recorder.name')->label(__('donations.receipt.recorded_by'))->placeholder('—'),
                TextEntry::make('cancel_reason')->label(__('donations.field.cancel_reason'))->placeholder('—')->columnSpanFull()
                    ->visible(fn (Donation $record): bool => $record->status === DonationStatus::Cancelled),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDonations::route('/'),
            'view' => ViewDonation::route('/{record}'),
        ];
    }
}
