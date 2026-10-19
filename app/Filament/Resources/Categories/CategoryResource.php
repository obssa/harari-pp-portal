<?php

namespace App\Filament\Resources\Categories;

use App\Domain\Content\Models\Category;
use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Filament\Support\Translatable;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 30;

    public static function getModelLabel(): string
    {
        return __('admin.categories.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.categories.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.content');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(3)->schema([
                Select::make('type')
                    ->label(__('admin.categories.field.type'))
                    ->options(collect(Category::TYPES)->mapWithKeys(fn (string $t): array => [$t => __("admin.categories.type.{$t}")])->all())
                    ->default(Category::TYPE_POST)
                    ->selectablePlaceholder(false),
                Select::make('parent_id')
                    ->label(__('admin.categories.field.parent'))
                    ->options(fn (?Category $record): array => self::parentOptions($record))
                    ->placeholder(__('admin.categories.field.no_parent')),
                TextInput::make('sort')
                    ->label(__('admin.categories.field.sort'))
                    ->numeric()->integer()->minValue(0)->maxValue(9999)
                    ->default(0),
            ]),
            Translatable::tabs(fn (string $locale): array => [
                Translatable::mainField($locale, 'name', 120)
                    ->label(__('admin.taxonomy.field.name')),
                Translatable::slugField($locale, 'category_translations'),
                Textarea::make("translations.{$locale}.description")
                    ->label(__('admin.taxonomy.field.description'))
                    ->rows(2)
                    ->maxLength(500),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['translations', 'parent.translations']))
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.taxonomy.field.name'))
                    ->state(fn (Category $record): string => $record->name())
                    ->description(fn (Category $record): ?string => $record->parent?->name()),
                TextColumn::make('languages')
                    ->label(__('admin.taxonomy.field.languages'))
                    ->state(fn (Category $record): array => array_map('strtoupper', $record->translatedLocales()))
                    ->badge(),
                TextColumn::make('type')
                    ->label(__('admin.categories.field.type'))
                    ->formatStateUsing(fn (string $state): string => __("admin.categories.type.{$state}"))
                    ->badge(),
                TextColumn::make('sort')
                    ->label(__('admin.categories.field.sort'))
                    ->sortable(),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, Category $record): array => [
                        ...$data,
                        'translations' => Translatable::fill($record, ['name', 'description']),
                    ])
                    ->using(fn (Category $record, array $data): Model => self::store($record, $data)),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function store(Category $record, array $data): Category
    {
        /** @var array<string, array<string, mixed>> $translations */
        $translations = (array) ($data['translations'] ?? []);
        Translatable::requireOne($translations, 'name');

        $record->fill([
            'type' => in_array($data['type'] ?? null, Category::TYPES, true) ? $data['type'] : Category::TYPE_POST,
            'parent_id' => filled($data['parent_id'] ?? null) ? $data['parent_id'] : null,
            'sort' => (int) ($data['sort'] ?? 0),
        ])->save();

        Translatable::save($record, $translations, 'name');

        return $record;
    }

    /**
     * @return array<string, string>
     */
    private static function parentOptions(?Category $record): array
    {
        return Category::query()
            ->with('translations')
            ->when($record, fn (Builder $query) => $query->whereKeyNot($record?->getKey()))
            ->whereNull('parent_id')
            ->orderBy('sort')
            ->get()
            ->mapWithKeys(fn (Category $category): array => [$category->getKey() => $category->name()])
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCategories::route('/'),
        ];
    }

    /**
     * @return Builder<Category>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Category> */
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
