<?php

namespace App\Filament\Resources\Leagues;

use App\Domain\Media\Enums\MediaKind;
use App\Domain\Organisation\Models\League;
use App\Filament\Resources\Leagues\Pages\CreateLeague;
use App\Filament\Resources\Leagues\Pages\EditLeague;
use App\Filament\Resources\Leagues\Pages\ListLeagues;
use App\Filament\Resources\Offices\OfficeResource;
use App\Filament\Support\DirectoryFields;
use App\Filament\Support\MediaPicker;
use App\Filament\Support\Translatable;
use App\Filament\Support\ViewOnSite;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class LeagueResource extends Resource
{
    protected static ?string $model = League::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?int $navigationSort = 12;

    public static function getModelLabel(): string
    {
        return __('admin.leagues.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.leagues.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.organisation');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 2])->schema([
                Translatable::tabs(fn (string $locale): array => [
                    Translatable::mainField($locale, 'name', 200)->label(__('admin.leagues.field.name')),
                    Translatable::slugField($locale, 'league_translations'),
                    Textarea::make("translations.{$locale}.summary")
                        ->label(__('admin.leagues.field.summary'))
                        ->rows(2)
                        ->maxLength(400),
                    DirectoryFields::body($locale, __('admin.leagues.field.body')),
                    DirectoryFields::published($locale, 'leagues.manage'),
                ]),
            ]),
            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 1])->schema([
                Section::make(__('admin.posts.section.details'))->schema([
                    Select::make('head_leader_id')
                        ->label(__('admin.leagues.field.head'))
                        ->searchable()
                        ->options(fn (): array => OfficeResource::leaderOptions()),
                    TextInput::make('sort')->label(__('admin.categories.field.sort'))->numeric()->integer()->minValue(0)->default(0),
                    Select::make('accent')
                        ->label(__('admin.leagues.field.accent'))
                        ->helperText(__('admin.leagues.help.accent'))
                        ->placeholder(__('admin.leagues.accent.auto'))
                        ->options(collect(League::ACCENTS)->mapWithKeys(fn (string $tone): array => [$tone => __("admin.leagues.accent.{$tone}")])->all()),
                    MediaPicker::make('logo_id', [MediaKind::Image])->label(__('admin.leagues.field.logo')),
                    MediaPicker::make('cover_image_id', [MediaKind::Image])->label(__('admin.offices.field.cover_image')),
                ]),
                Section::make(__('admin.leaders.section.contacts'))->collapsed()->schema([
                    TextInput::make('phone')->label(__('admin.offices.field.phone'))->tel()->maxLength(40),
                    TextInput::make('email')->label(__('admin.offices.field.email'))->email()->maxLength(190),
                    ...DirectoryFields::socials(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['translations', 'head.translations']))
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.leagues.field.name'))
                    ->state(fn (League $record): string => $record->name()),
                TextColumn::make('head')
                    ->label(__('admin.leagues.field.head'))
                    ->state(fn (League $record): ?string => $record->head?->name()),
                DirectoryFields::languagesColumn(),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([ViewOnSite::row(), EditAction::make(), DeleteAction::make(), RestoreAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeagues::route('/'),
            'create' => CreateLeague::route('/create'),
            'edit' => EditLeague::route('/{record}/edit'),
        ];
    }

    /**
     * @return Builder<League>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<League> */
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
