<?php

namespace App\Filament\Resources\Videos;

use App\Domain\Content\Models\Category;
use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Models\Video;
use App\Domain\Organisation\Models\League;
use App\Domain\Organisation\Models\Office;
use App\Filament\Resources\Videos\Pages\CreateVideo;
use App\Filament\Resources\Videos\Pages\EditVideo;
use App\Filament\Resources\Videos\Pages\ListVideos;
use App\Filament\Support\CalendarDate;
use App\Filament\Support\DirectoryFields;
use App\Filament\Support\MediaPicker;
use App\Filament\Support\Translatable;
use App\Filament\Support\ViewOnSite;
use App\Services\Calendar\EthiopianCalendar;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class VideoResource extends Resource
{
    protected static ?string $model = Video::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedVideoCamera;

    protected static ?int $navigationSort = 41;

    public static function getModelLabel(): string
    {
        return __('video.admin.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('video.admin.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.content');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 2])->schema([
                Section::make(__('video.admin.field.url'))->schema([
                    TextInput::make('url')
                        ->hiddenLabel()
                        ->placeholder('https://www.youtube.com/watch?v=…')
                        ->helperText(__('video.admin.help.url'))
                        ->url()
                        ->required()
                        ->maxLength(500),
                ]),
                Translatable::tabs(fn (string $locale): array => [
                    Translatable::mainField($locale, 'title', 200)->label(__('admin.posts.field.title')),
                    Translatable::slugField($locale, 'video_translations'),
                    Textarea::make("translations.{$locale}.summary")
                        ->label(__('video.admin.field.summary'))
                        ->rows(3)
                        ->maxLength(600),
                    DirectoryFields::published($locale, 'videos.manage'),
                ]),
            ]),
            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 1])->schema([
                Section::make(__('admin.posts.section.details'))->schema([
                    MediaPicker::make('thumbnail_id', [MediaKind::Image])
                        ->label(__('video.admin.field.thumbnail'))
                        ->helperText(__('video.admin.help.thumbnail')),
                    Select::make('category_id')
                        ->label(__('video.admin.field.playlist'))
                        ->options(fn (): array => Category::query()->where('type', Category::TYPE_VIDEO)->with('translations')->orderBy('sort')->get()
                            ->mapWithKeys(fn (Category $c): array => [$c->id => $c->name()])->all()),
                    DatePicker::make('recorded_on')->label(__('video.admin.field.recorded_on')),
                    Select::make('office_id')
                        ->label(__('admin.leaders.field.office'))
                        ->searchable()
                        ->options(fn (): array => Office::query()->with('translations')->orderBy('sort')->get()
                            ->mapWithKeys(fn (Office $o): array => [$o->id => $o->name()])->all()),
                    Select::make('league_id')
                        ->label(__('admin.leaders.field.league'))
                        ->options(fn (): array => League::query()->with('translations')->orderBy('sort')->get()
                            ->mapWithKeys(fn (League $l): array => [$l->id => $l->name()])->all()),
                    Toggle::make('is_featured')->label(__('video.admin.field.is_featured'))->helperText(__('video.admin.help.is_featured')),
                ]),
                Section::make(__('video.admin.section.live'))->collapsible()->schema([
                    Toggle::make('is_live')->label(__('video.admin.field.is_live'))->live(),
                    DateTimePicker::make('live_starts_at')->label(__('video.admin.field.live_starts_at'))->seconds(false)->timezone(EthiopianCalendar::timezone())
                        ->required(fn (Get $get): bool => (bool) $get('is_live'))
                        ->visible(fn (Get $get): bool => (bool) $get('is_live')),
                    DateTimePicker::make('live_ends_at')->label(__('video.admin.field.live_ends_at'))->seconds(false)->timezone(EthiopianCalendar::timezone())
                        ->helperText(__('video.admin.help.live_ends_at', ['hours' => Video::LIVE_HOURS]))
                        ->visible(fn (Get $get): bool => (bool) $get('is_live')),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('translations'))
            ->columns([
                TextColumn::make('title')->label(__('admin.posts.field.title'))
                    ->state(fn (Video $record): string => $record->title()),
                TextColumn::make('provider')->label(__('video.admin.field.provider'))
                    ->formatStateUsing(fn (string $state): string => __("video.provider.{$state}"))->badge(),
                IconColumn::make('is_live')->label(__('video.admin.field.is_live'))->boolean(),
                TextColumn::make('recorded_on')->label(__('video.admin.field.recorded_on'))
                    ->formatStateUsing(CalendarDate::format())->sortable(),
                DirectoryFields::languagesColumn(),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([ViewOnSite::row(), EditAction::make(), DeleteAction::make(), RestoreAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVideos::route('/'),
            'create' => CreateVideo::route('/create'),
            'edit' => EditVideo::route('/{record}/edit'),
        ];
    }

    /**
     * @return Builder<Video>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Video> */
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
