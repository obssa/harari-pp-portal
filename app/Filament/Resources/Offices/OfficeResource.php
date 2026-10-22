<?php

namespace App\Filament\Resources\Offices;

use App\Domain\Media\Enums\MediaKind;
use App\Domain\Organisation\Enums\OfficeType;
use App\Domain\Organisation\Models\Leader;
use App\Domain\Organisation\Models\Office;
use App\Filament\Resources\Offices\Pages\CreateOffice;
use App\Filament\Resources\Offices\Pages\EditOffice;
use App\Filament\Resources\Offices\Pages\ListOffices;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class OfficeResource extends Resource
{
    protected static ?string $model = Office::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 10;

    public static function getModelLabel(): string
    {
        return __('admin.offices.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.offices.plural');
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
                    Translatable::mainField($locale, 'name', 200)->label(__('admin.offices.field.name')),
                    Translatable::slugField($locale, 'office_translations'),
                    Textarea::make("translations.{$locale}.summary")
                        ->label(__('admin.offices.field.summary'))
                        ->rows(2)
                        ->maxLength(400),
                    DirectoryFields::body($locale, __('admin.offices.field.body')),
                    Textarea::make("translations.{$locale}.address")
                        ->label(__('admin.offices.field.address'))
                        ->rows(2)
                        ->maxLength(400),
                    TextInput::make("translations.{$locale}.hours")
                        ->label(__('admin.offices.field.hours'))
                        ->placeholder(__('admin.offices.help.hours'))
                        ->maxLength(200),
                    DirectoryFields::published($locale, 'offices.manage'),
                ]),
            ]),
            Section::make(__('admin.posts.section.details'))->columnSpan(['default' => 3, 'lg' => 1])->schema([
                Select::make('type')
                    ->label(__('admin.offices.field.type'))
                    ->options(OfficeType::options())
                    ->required(),
                Select::make('parent_id')
                    ->label(__('admin.offices.field.parent'))
                    ->placeholder(__('admin.offices.field.no_parent'))
                    ->searchable()
                    ->options(fn (?Office $record): array => Office::query()->with('translations')
                        ->when($record, fn (Builder $q) => $q->whereKeyNot($record?->getKey()))
                        ->orderBy('type')->orderBy('sort')->get()
                        ->mapWithKeys(fn (Office $o): array => [$o->id => $o->name().' · '.$o->type->label()])->all()),
                Select::make('head_leader_id')
                    ->label(__('admin.offices.field.head'))
                    ->searchable()
                    ->options(fn (): array => self::leaderOptions()),
                TextInput::make('phone')->label(__('admin.offices.field.phone'))->tel()->maxLength(40),
                TextInput::make('email')->label(__('admin.offices.field.email'))->email()->maxLength(190),
                Grid::make(2)->schema([
                    TextInput::make('latitude')->label(__('admin.offices.field.latitude'))->numeric()->minValue(-90)->maxValue(90),
                    TextInput::make('longitude')->label(__('admin.offices.field.longitude'))->numeric()->minValue(-180)->maxValue(180),
                ]),
                TextInput::make('sort')->label(__('admin.categories.field.sort'))->numeric()->integer()->minValue(0)->default(0),
                MediaPicker::make('cover_image_id', [MediaKind::Image])->label(__('admin.offices.field.cover_image')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['translations', 'parent.translations', 'head.translations']))
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.offices.field.name'))
                    ->state(fn (Office $record): string => $record->name())
                    ->description(fn (Office $record): ?string => $record->parent?->name()),
                TextColumn::make('type')
                    ->label(__('admin.offices.field.type'))
                    ->formatStateUsing(fn (OfficeType $state): string => $state->label())
                    ->badge(),
                TextColumn::make('head')
                    ->label(__('admin.offices.field.head'))
                    ->state(fn (Office $record): ?string => $record->head?->name()),
                DirectoryFields::languagesColumn(),
            ])
            ->filters([
                SelectFilter::make('type')->label(__('admin.offices.field.type'))->options(OfficeType::options()),
                TrashedFilter::make(),
            ])
            ->recordActions([ViewOnSite::row(), EditAction::make(), DeleteAction::make(), RestoreAction::make()]);
    }

    /**
     * @return array<string, string>
     */
    public static function leaderOptions(): array
    {
        return Leader::query()->with('translations')->orderBy('rank')->get()
            ->mapWithKeys(fn (Leader $leader): array => [$leader->id => $leader->name()])
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOffices::route('/'),
            'create' => CreateOffice::route('/create'),
            'edit' => EditOffice::route('/{record}/edit'),
        ];
    }

    /**
     * @return Builder<Office>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Office> */
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
