<?php

namespace App\Filament\Resources\Menus;

use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\Post;
use App\Domain\Translation\Support\Locales;
use App\Filament\Resources\Menus\Pages\ManageMenus;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Menus (PLAN.md §7): header, footer and utility bar, each a list of items
 * with labels per language and up to one level of sub-items.
 */
class MenuResource extends Resource
{
    protected static ?string $model = Menu::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected static ?int $navigationSort = 35;

    public static function getModelLabel(): string
    {
        return __('admin.menus.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.menus.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.content');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('location')
                ->label(__('admin.menus.field.location'))
                ->options(fn (?Menu $record): array => collect(Menu::LOCATIONS)
                    ->reject(fn (string $l): bool => $record === null && Menu::query()->where('location', $l)->exists())
                    ->mapWithKeys(fn (string $l): array => [$l => __("admin.menus.location.{$l}")])->all())
                ->disabled(fn (?Menu $record): bool => $record !== null)
                ->required(),
            self::itemsRepeater('items', withChildren: true),
        ]);
    }

    public static function itemsRepeater(string $name, bool $withChildren): Repeater
    {
        $fields = [
            Select::make('type')
                ->label(__('admin.menus.field.type'))
                ->options(collect(Menu::TYPES)->mapWithKeys(fn (string $t): array => [$t => __("admin.menus.type.{$t}")])->all())
                ->default('page')
                ->required()
                ->live(),
            Select::make('page_id')
                ->label(__('admin.pages.singular'))
                ->options(fn (): array => Page::query()->with('translations')->orderBy('sort')->get()
                    ->mapWithKeys(fn (Page $p): array => [$p->id => $p->title()])->all())
                ->searchable()
                ->visible(fn (Get $get): bool => $get('type') === 'page')
                ->required(fn (Get $get): bool => $get('type') === 'page'),
            Select::make('post_id')
                ->label(__('admin.posts.singular'))
                ->options(fn (): array => Post::query()->with('translations')->latest()->limit(100)->get()
                    ->mapWithKeys(fn (Post $p): array => [$p->id => $p->title()])->all())
                ->searchable()
                ->visible(fn (Get $get): bool => $get('type') === 'post')
                ->required(fn (Get $get): bool => $get('type') === 'post'),
            TextInput::make('url')
                ->label(__('admin.menus.field.url'))
                ->helperText(__('admin.menus.help.url'))
                ->maxLength(500)
                ->regex('~^(/(?!/)|https://)~')
                ->visible(fn (Get $get): bool => $get('type') === 'url')
                ->required(fn (Get $get): bool => $get('type') === 'url'),
            Fieldset::make(__('admin.menus.field.labels'))
                ->columns(2)
                ->schema(array_map(
                    fn (string $locale): TextInput => TextInput::make("labels.{$locale}")
                        ->label(Locales::nativeName($locale))
                        ->maxLength(60),
                    Locales::all(),
                )),
        ];

        if ($withChildren) {
            $fields[] = self::itemsRepeater('children', withChildren: false)
                ->label(__('admin.menus.field.children'));
        }

        return Repeater::make($name)
            ->label(__('admin.menus.field.items'))
            ->schema($fields)
            ->maxItems(Menu::MAX_ITEMS)
            ->collapsible()
            ->cloneable()
            ->itemLabel(fn (array $state): ?string => collect((array) ($state['labels'] ?? []))->filter()->first())
            ->defaultItems(0);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('location')->label(__('admin.menus.field.location'))
                    ->formatStateUsing(fn (string $state): string => __("admin.menus.location.{$state}")),
                TextColumn::make('items')->label(__('admin.menus.field.items'))
                    ->state(fn (Menu $record): int => count($record->items)),
            ])
            ->recordActions([EditAction::make()->mutateDataUsing(fn (array $data): array => [...$data, 'updated_by' => auth()->id()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageMenus::route('/')];
    }
}
