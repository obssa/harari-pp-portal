<?php

namespace App\Filament\Resources\Galleries;

use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Models\Gallery;
use App\Domain\Organisation\Models\League;
use App\Domain\Organisation\Models\Office;
use App\Domain\Translation\Support\Locales;
use App\Filament\Resources\Galleries\Pages\CreateGallery;
use App\Filament\Resources\Galleries\Pages\EditGallery;
use App\Filament\Resources\Galleries\Pages\ListGalleries;
use App\Filament\Support\CalendarDate;
use App\Filament\Support\DirectoryFields;
use App\Filament\Support\MediaPicker;
use App\Filament\Support\Translatable;
use App\Filament\Support\ViewOnSite;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
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

class GalleryResource extends Resource
{
    protected static ?string $model = Gallery::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?int $navigationSort = 40;

    public static function getModelLabel(): string
    {
        return __('gallery.admin.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('gallery.admin.plural');
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
                    Translatable::mainField($locale, 'title', 200)->label(__('admin.posts.field.title')),
                    Translatable::slugField($locale, 'gallery_translations'),
                    Textarea::make("translations.{$locale}.summary")
                        ->label(__('gallery.admin.field.summary'))
                        ->rows(2)
                        ->maxLength(400),
                    DirectoryFields::published($locale, 'galleries.manage'),
                ]),
                Section::make(__('gallery.admin.field.photos'))->schema([
                    Repeater::make('items')
                        ->hiddenLabel()
                        ->reorderableWithDragAndDrop()
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->maxItems(Gallery::MAX_ITEMS)
                        ->addActionLabel(__('gallery.admin.add_photo'))
                        ->itemLabel(fn (array $state): ?string => filled($state['caption'][app()->getLocale()] ?? null) ? (string) $state['caption'][app()->getLocale()] : null)
                        ->schema([
                            MediaPicker::make('media_id', [MediaKind::Image])->label(__('content.block.field.image'))->required(),
                            Fieldset::make(__('content.block.field.caption'))
                                ->columns(2)
                                ->schema(array_map(fn (string $locale): TextInput => TextInput::make("caption.{$locale}")
                                    ->label(Locales::nativeName($locale))
                                    ->extraInputAttributes(['lang' => $locale])
                                    ->maxLength(300), Locales::all())),
                        ]),
                ]),
            ]),
            Section::make(__('admin.posts.section.details'))->columnSpan(['default' => 3, 'lg' => 1])->schema([
                MediaPicker::make('cover_id', [MediaKind::Image])
                    ->label(__('gallery.admin.field.cover'))
                    ->helperText(__('gallery.admin.help.cover')),
                DatePicker::make('taken_on')->label(__('gallery.admin.field.taken_on')),
                Select::make('office_id')
                    ->label(__('admin.leaders.field.office'))
                    ->searchable()
                    ->options(fn (): array => Office::query()->with('translations')->orderBy('sort')->get()
                        ->mapWithKeys(fn (Office $o): array => [$o->id => $o->name()])->all()),
                Select::make('league_id')
                    ->label(__('admin.leaders.field.league'))
                    ->options(fn (): array => League::query()->with('translations')->orderBy('sort')->get()
                        ->mapWithKeys(fn (League $l): array => [$l->id => $l->name()])->all()),
                Toggle::make('allow_download')
                    ->label(__('gallery.admin.field.allow_download'))
                    ->helperText(__('gallery.admin.help.allow_download')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('taken_on', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('translations')->withCount('items'))
            ->columns([
                TextColumn::make('title')->label(__('admin.posts.field.title'))
                    ->state(fn (Gallery $record): string => $record->title()),
                TextColumn::make('items_count')->label(__('gallery.admin.field.photos'))->numeric(),
                TextColumn::make('taken_on')->label(__('gallery.admin.field.taken_on'))
                    ->formatStateUsing(CalendarDate::format())->sortable(),
                DirectoryFields::languagesColumn(),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([ViewOnSite::row(), EditAction::make(), DeleteAction::make(), RestoreAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGalleries::route('/'),
            'create' => CreateGallery::route('/create'),
            'edit' => EditGallery::route('/{record}/edit'),
        ];
    }

    /**
     * @return Builder<Gallery>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Gallery> */
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
