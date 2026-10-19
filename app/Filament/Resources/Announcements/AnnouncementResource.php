<?php

namespace App\Filament\Resources\Announcements;

use App\Domain\Dashboard\Models\Announcement;
use App\Filament\Resources\Announcements\Pages\ManageAnnouncements;
use App\Filament\Support\CalendarDate;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Internal notices shown on every staff dashboard (PLAN.md §7).
 */
class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?int $navigationSort = 45;

    public static function getModelLabel(): string
    {
        return __('dashboard.announcements.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('dashboard.announcements.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.administration');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label(__('dashboard.announcements.field.title'))->required()->maxLength(160)->columnSpanFull(),
            Textarea::make('body')->label(__('dashboard.announcements.field.body'))->rows(4)->maxLength(2000)->columnSpanFull(),
            Grid::make(3)->columnSpanFull()->schema([
                Select::make('tone')->label(__('dashboard.announcements.field.tone'))
                    ->options(collect(Announcement::TONES)->mapWithKeys(fn (string $t): array => [$t => __("dashboard.tone.{$t}")])->all())
                    ->default('info')
                    ->required(),
                DateTimePicker::make('starts_at')->label(__('dashboard.announcements.field.starts_at'))->seconds(false),
                DateTimePicker::make('ends_at')->label(__('dashboard.announcements.field.ends_at'))->seconds(false)->after('starts_at'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('title')->label(__('dashboard.announcements.field.title'))->searchable(),
                TextColumn::make('tone')->label(__('dashboard.announcements.field.tone'))
                    ->formatStateUsing(fn (string $state): string => __("dashboard.tone.{$state}"))->badge(),
                TextColumn::make('starts_at')->label(__('dashboard.announcements.field.starts_at'))
                    ->formatStateUsing(CalendarDate::format(withTime: true))->placeholder('—'),
                TextColumn::make('ends_at')->label(__('dashboard.announcements.field.ends_at'))
                    ->formatStateUsing(CalendarDate::format(withTime: true))->placeholder('—'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAnnouncements::route('/'),
        ];
    }
}
