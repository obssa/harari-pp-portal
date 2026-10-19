<?php

namespace App\Filament\Resources\Pages;

use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\PageTranslation;
use App\Domain\Content\Support\BodyRenderer;
use App\Domain\Media\Enums\MediaKind;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Filament\Support\CalendarDate;
use App\Filament\Support\CurrentUser;
use App\Filament\Support\MediaPicker;
use App\Filament\Support\Translatable;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('admin.pages.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.pages.plural');
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
                    Translatable::slugField($locale, 'page_translations'),
                    Textarea::make("translations.{$locale}.excerpt")
                        ->label(__('admin.posts.field.excerpt'))
                        ->rows(2)
                        ->maxLength(400),
                    RichEditor::make("translations.{$locale}.body")
                        ->label(__('admin.posts.field.body'))
                        ->json()
                        ->customBlocks(BodyRenderer::BLOCKS)
                        ->toolbarButtons([['bold', 'italic', 'link'], ['h2', 'h3'], ['blockquote', 'bulletList', 'orderedList'], ['table'], ['undo', 'redo']])
                        ->extraInputAttributes(['lang' => $locale]),
                    Toggle::make("translations.{$locale}.published")
                        ->label(__('admin.pages.field.published'))
                        ->helperText(__('admin.pages.help.published'))
                        ->disabled(fn (): bool => ! CurrentUser::get()->can('pages.publish')),
                    Section::make(__('admin.posts.section.seo'))->collapsed()->compact()->schema([
                        TextInput::make("translations.{$locale}.seo_title")->label(__('admin.posts.field.seo_title'))->maxLength(70),
                        Textarea::make("translations.{$locale}.seo_description")->label(__('admin.posts.field.seo_description'))->rows(2)->maxLength(170),
                        Toggle::make("translations.{$locale}.noindex")->label(__('admin.posts.field.noindex')),
                    ]),
                ]),
            ]),
            Section::make(__('admin.posts.section.details'))->columnSpan(['default' => 3, 'lg' => 1])->schema([
                Select::make('template')
                    ->label(__('admin.pages.field.template'))
                    ->options(collect(Page::TEMPLATES)->mapWithKeys(fn (string $t): array => [$t => __("admin.pages.template.{$t}")])->all())
                    ->default('default')
                    ->required(),
                Select::make('parent_id')
                    ->label(__('admin.pages.field.parent'))
                    ->placeholder(__('admin.pages.field.no_parent'))
                    ->options(fn (?Page $record): array => Page::query()->with('translations')
                        ->when($record, fn (Builder $q) => $q->whereKeyNot($record?->getKey()))
                        ->orderBy('sort')->get()
                        ->mapWithKeys(fn (Page $p): array => [$p->id => $p->title()])->all()),
                TextInput::make('sort')->label(__('admin.categories.field.sort'))->numeric()->integer()->minValue(0)->default(0),
                MediaPicker::make('featured_image_id', [MediaKind::Image])->label(__('admin.posts.field.featured_image')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['translations', 'parent.translations']))
            ->columns([
                TextColumn::make('title')
                    ->label(__('admin.posts.field.title'))
                    ->state(fn (Page $record): string => $record->title())
                    ->description(fn (Page $record): ?string => $record->parent?->title()),
                TextColumn::make('languages')
                    ->label(__('admin.posts.field.languages'))
                    ->state(fn (Page $record): array => $record->translations
                        ->map(fn (PageTranslation $t): string => strtoupper($t->locale).' · '.$t->status->label())->values()->all())
                    ->badge(),
                TextColumn::make('template')->label(__('admin.pages.field.template'))
                    ->formatStateUsing(fn (string $state): string => __("admin.pages.template.{$state}")),
                TextColumn::make('updated_at')->label(__('admin.posts.field.updated_at'))
                    ->formatStateUsing(CalendarDate::format(withTime: true))->sortable(),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([EditAction::make(), DeleteAction::make(), RestoreAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }

    /**
     * @return Builder<Page>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Page> */
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
