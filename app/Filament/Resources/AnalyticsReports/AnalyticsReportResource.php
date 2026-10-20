<?php

namespace App\Filament\Resources\AnalyticsReports;

use App\Domain\Analytics\Actions\SendAnalyticsReport;
use App\Domain\Analytics\Models\AnalyticsReport;
use App\Domain\Analytics\Support\TrafficReport;
use App\Domain\Translation\Support\Locales;
use App\Filament\Resources\AnalyticsReports\Pages\ManageAnalyticsReports;
use App\Filament\Support\CalendarDate;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Traffic reports sent by email every week or month (ADR-0040).
 */
class AnalyticsReportResource extends Resource
{
    protected static ?string $model = AnalyticsReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelopeOpen;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('analytics.schedule.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('analytics.schedule.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.insights');
    }

    /**
     * @return array<mixed>
     */
    public static function fields(): array
    {
        return [
            TextInput::make('name')->label(__('analytics.schedule.name'))->required()->maxLength(120),
            Select::make('dimension')->label(__('analytics.column.dimension'))->required()->default('pages')
                ->options(collect(TrafficReport::DIMENSIONS)->mapWithKeys(fn (string $d): array => [$d => TrafficReport::dimensionLabel($d)])->all()),
            Select::make('locale_filter')->label(__('analytics.column.language'))->placeholder(__('analytics.all_languages'))
                ->options(collect(Locales::all())->mapWithKeys(fn (string $l): array => [$l => Locales::nativeName($l)])->all()),
            Select::make('frequency')->label(__('analytics.schedule.frequency'))->required()->default(AnalyticsReport::WEEKLY)
                ->options([AnalyticsReport::WEEKLY => __('analytics.schedule.weekly'), AnalyticsReport::MONTHLY => __('analytics.schedule.monthly')]),
            TagsInput::make('recipients')->label(__('analytics.schedule.recipients'))->helperText(__('analytics.schedule.recipients_help'))
                ->required()->nestedRecursiveRules(['email:rfc', 'max:190'])->rules(['array', 'max:'.AnalyticsReport::MAX_RECIPIENTS])->columnSpanFull(),
            Select::make('mail_locale')->label(__('analytics.schedule.mail_locale'))->required()->default(fn (): string => app()->getLocale())
                ->options(collect(['en', 'am', 'om'])->mapWithKeys(fn (string $l): array => [$l => Locales::nativeName($l)])->all()),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components(self::fields());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label(__('analytics.schedule.name'))->searchable(),
                TextColumn::make('dimension')->label(__('analytics.column.dimension'))->formatStateUsing(fn (string $state): string => TrafficReport::dimensionLabel($state)),
                TextColumn::make('frequency')->label(__('analytics.schedule.frequency'))->badge()
                    ->formatStateUsing(fn (string $state): string => $state === AnalyticsReport::MONTHLY ? __('analytics.schedule.monthly') : __('analytics.schedule.weekly')),
                TextColumn::make('recipients')->label(__('analytics.schedule.recipients'))->formatStateUsing(fn (mixed $state, AnalyticsReport $record): string => (string) count($record->recipients)),
                TextColumn::make('last_sent_at')->label(__('analytics.schedule.last_sent'))->formatStateUsing(CalendarDate::format(withTime: true))->placeholder('—'),
            ])
            ->recordActions([
                Action::make('sendNow')
                    ->label(__('analytics.schedule.send_now'))
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('gray')
                    ->authorize('update')
                    ->requiresConfirmation()
                    ->action(function (AnalyticsReport $record): void {
                        $sent = app(SendAnalyticsReport::class)->handle($record);
                        Notification::make()->success()->title(__('analytics.schedule.sent', ['count' => $sent]))->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAnalyticsReports::route('/')];
    }
}
