<?php

namespace App\Filament\Resources\Tags;

use App\Domain\Content\Models\Tag;
use App\Filament\Resources\Tags\Pages\ManageTags;
use App\Filament\Support\Translatable;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class TagResource extends Resource
{
    protected static ?string $model = Tag::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHashtag;

    protected static ?int $navigationSort = 31;

    public static function getModelLabel(): string
    {
        return __('admin.tags.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.tags.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.content');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Translatable::tabs(fn (string $locale): array => [
                Translatable::mainField($locale, 'name', 80)
                    ->label(__('admin.taxonomy.field.name')),
                Translatable::slugField($locale, 'tag_translations'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('translations'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.taxonomy.field.name'))
                    ->state(fn (Tag $record): string => $record->name()),
                TextColumn::make('languages')
                    ->label(__('admin.taxonomy.field.languages'))
                    ->state(fn (Tag $record): array => array_map('strtoupper', $record->translatedLocales()))
                    ->badge(),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, Tag $record): array => [
                        ...$data,
                        'translations' => Translatable::fill($record, ['name']),
                    ])
                    ->using(fn (Tag $record, array $data): Model => self::store($record, $data)),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function store(Tag $record, array $data): Tag
    {
        /** @var array<string, array<string, mixed>> $translations */
        $translations = (array) ($data['translations'] ?? []);
        Translatable::requireOne($translations, 'name');

        $record->save();
        Translatable::save($record, $translations, 'name');

        return $record;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTags::route('/'),
        ];
    }

    /**
     * @return Builder<Tag>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Tag> */
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
