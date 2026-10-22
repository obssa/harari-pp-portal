<?php

namespace App\Filament\Resources\Leaders;

use App\Domain\Media\Enums\MediaKind;
use App\Domain\Organisation\Enums\LeaderBody;
use App\Domain\Organisation\Models\Leader;
use App\Domain\Organisation\Models\League;
use App\Domain\Organisation\Models\Office;
use App\Filament\Resources\Leaders\Pages\CreateLeader;
use App\Filament\Resources\Leaders\Pages\EditLeader;
use App\Filament\Resources\Leaders\Pages\ListLeaders;
use App\Filament\Support\DirectoryFields;
use App\Filament\Support\MediaPicker;
use App\Filament\Support\Translatable;
use App\Filament\Support\ViewOnSite;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DatePicker;
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

class LeaderResource extends Resource
{
    protected static ?string $model = Leader::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 11;

    public static function getModelLabel(): string
    {
        return __('admin.leaders.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.leaders.plural');
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
                    Translatable::mainField($locale, 'name', 200)->label(__('admin.leaders.field.name')),
                    Translatable::slugField($locale, 'leader_translations'),
                    TextInput::make("translations.{$locale}.position")
                        ->label(__('admin.leaders.field.position'))
                        ->helperText(__('admin.leaders.help.position'))
                        ->maxLength(200),
                    Textarea::make("translations.{$locale}.summary")
                        ->label(__('admin.leaders.field.summary'))
                        ->rows(2)
                        ->maxLength(400),
                    DirectoryFields::body($locale, __('admin.leaders.field.body')),
                    DirectoryFields::published($locale, 'leaders.manage'),
                ]),
            ]),
            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 1])->schema([
                Section::make(__('admin.posts.section.details'))->schema([
                    Select::make('body')
                        ->label(__('admin.leaders.field.body_group'))
                        ->options(LeaderBody::options())
                        ->required(),
                    TextInput::make('rank')
                        ->label(__('admin.leaders.field.rank'))
                        ->helperText(__('admin.leaders.help.rank'))
                        ->numeric()->integer()->minValue(0)->maxValue(9999)
                        ->default(100),
                    Select::make('office_id')
                        ->label(__('admin.leaders.field.office'))
                        ->searchable()
                        ->options(fn (): array => Office::query()->with('translations')->orderBy('sort')->get()
                            ->mapWithKeys(fn (Office $o): array => [$o->id => $o->name()])->all()),
                    Select::make('league_id')
                        ->label(__('admin.leaders.field.league'))
                        ->options(fn (): array => League::query()->with('translations')->orderBy('sort')->get()
                            ->mapWithKeys(fn (League $l): array => [$l->id => $l->name()])->all()),
                    Grid::make(2)->schema([
                        DatePicker::make('term_start')->label(__('admin.leaders.field.term_start')),
                        DatePicker::make('term_end')->label(__('admin.leaders.field.term_end'))->afterOrEqual('term_start'),
                    ]),
                    MediaPicker::make('portrait_id', [MediaKind::Image])->label(__('admin.leaders.field.portrait')),
                    Grid::make(2)->schema([
                        TextInput::make('source_url')->label(__('admin.leaders.field.source_url'))
                            ->helperText(__('admin.leaders.help.source_url'))->url()->maxLength(500),
                        DatePicker::make('source_date')->label(__('admin.leaders.field.source_date')),
                    ]),
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
            ->defaultSort('rank')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['translations', 'office.translations']))
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.leaders.field.name'))
                    ->state(fn (Leader $record): string => $record->name())
                    ->description(fn (Leader $record): ?string => $record->translation()?->getAttribute('position')),
                TextColumn::make('body')
                    ->label(__('admin.leaders.field.body_group'))
                    ->formatStateUsing(fn (LeaderBody $state): string => $state->label())
                    ->badge(),
                TextColumn::make('office')
                    ->label(__('admin.leaders.field.office'))
                    ->state(fn (Leader $record): ?string => $record->office?->name()),
                TextColumn::make('rank')->label(__('admin.leaders.field.rank'))->sortable(),
                DirectoryFields::languagesColumn(),
            ])
            ->filters([
                SelectFilter::make('body')->label(__('admin.leaders.field.body_group'))->options(LeaderBody::options()),
                TrashedFilter::make(),
            ])
            ->recordActions([ViewOnSite::row(), EditAction::make(), DeleteAction::make(), RestoreAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeaders::route('/'),
            'create' => CreateLeader::route('/create'),
            'edit' => EditLeader::route('/{record}/edit'),
        ];
    }

    /**
     * @return Builder<Leader>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Leader> */
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
