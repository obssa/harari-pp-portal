<?php

namespace App\Filament\Resources\Statistics;

use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\Statistic;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Statistics\Pages\CreateStatistic;
use App\Filament\Resources\Statistics\Pages\EditStatistic;
use App\Filament\Resources\Statistics\Pages\ListStatistics;
use App\Filament\Support\CalendarDate;
use App\Filament\Support\Translatable;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class StatisticResource extends Resource
{
    protected static ?string $model = Statistic::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 24;

    public static function getModelLabel(): string
    {
        return __('projects.statistics.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('projects.statistics.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.content');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 2])->schema([
                Translatable::tabs(fn (string $locale): array => [
                    Translatable::mainField($locale, 'label', 200)->label(__('projects.statistics.field.label'))
                        ->helperText(__('projects.statistics.help.label')),
                    TextInput::make("translations.{$locale}.unit")->label(__('projects.statistics.field.unit'))
                        ->helperText(__('projects.statistics.help.unit'))->maxLength(40),
                    TextInput::make("translations.{$locale}.source")->label(__('projects.statistics.field.source'))
                        ->helperText(__('projects.statistics.help.source'))->maxLength(300),
                ]),
            ]),
            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 1])->schema([
                Section::make(__('admin.posts.section.details'))->schema([
                    TextInput::make('value')->label(__('projects.statistics.field.value'))->numeric()->required(),
                    DatePicker::make('as_of')->label(__('projects.statistics.field.as_of')),
                    TextInput::make('source_url')->label(__('projects.statistics.field.source_url'))->url()->startsWith(['https://'])->maxLength(500),
                    Select::make('sector_id')->label(__('projects.admin.field.sector'))->options(fn (): array => ProjectResource::sectorOptions()),
                    Select::make('project_id')->label(__('projects.statistics.field.project'))->searchable()
                        ->options(fn (): array => Project::query()->with('translations')->latest()->limit(300)->get()
                            ->mapWithKeys(fn (Project $p): array => [$p->id => $p->title()])->all()),
                    Toggle::make('on_achievements')->label(__('projects.statistics.field.on_achievements'))->default(true),
                    TextInput::make('sort')->label(__('projects.statistics.field.sort'))->numeric()->integer()->default(0),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['translations', 'sector.translations', 'project.translations']))
            ->columns([
                TextColumn::make('label')->label(__('projects.statistics.field.label'))
                    ->state(fn (Statistic $record): string => $record->label())->wrap(),
                TextColumn::make('value')->label(__('projects.statistics.field.value'))
                    ->state(fn (Statistic $record): string => trim($record->formatted().' '.$record->translationOrAny()?->getAttribute('unit'))),
                TextColumn::make('sector')->label(__('projects.admin.field.sector'))
                    ->state(fn (Statistic $record): ?string => $record->sector?->name())->placeholder('—'),
                TextColumn::make('project')->label(__('projects.statistics.field.project'))
                    ->state(fn (Statistic $record): ?string => $record->project?->title())->placeholder('—')->wrap(),
                TextColumn::make('as_of')->label(__('projects.statistics.field.as_of'))
                    ->formatStateUsing(CalendarDate::format())->placeholder('—'),
                IconColumn::make('on_achievements')->label(__('projects.statistics.field.on_achievements'))->boolean(),
            ])
            ->filters([
                SelectFilter::make('sector_id')->label(__('projects.admin.field.sector'))->options(fn (): array => ProjectResource::sectorOptions()),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStatistics::route('/'),
            'create' => CreateStatistic::route('/create'),
            'edit' => EditStatistic::route('/{record}/edit'),
        ];
    }
}
