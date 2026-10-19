<?php

namespace App\Filament\Resources\MediaFolders;

use App\Domain\Media\Models\MediaFolder;
use App\Domain\Sites\Models\Site;
use App\Filament\Resources\MediaFolders\Pages\ManageMediaFolders;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class MediaFolderResource extends Resource
{
    protected static ?string $model = MediaFolder::class;

    protected static ?string $slug = 'media-folders';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 11;

    public static function getModelLabel(): string
    {
        return __('admin.media_folders.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.media_folders.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.content');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('admin.media_folders.field.name'))
                ->required()
                ->maxLength(120)
                ->rules([
                    fn (?MediaFolder $record, callable $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record, $get): void {
                        $parent = filled($get('parent_id')) ? (int) $get('parent_id') : null;
                        $taken = MediaFolder::query()
                            ->where('site_id', Site::current()->id)
                            ->where('parent_id', $parent)
                            ->where('name', trim((string) $value))
                            ->when($record, fn (Builder $q) => $q->whereKeyNot($record?->getKey()))
                            ->exists();

                        if ($taken) {
                            $fail(__('admin.media_folders.name_taken'));
                        }
                    },
                ]),
            Select::make('parent_id')
                ->label(__('admin.media_folders.field.parent'))
                ->placeholder(__('admin.media_folders.root'))
                ->options(fn (?MediaFolder $record): array => MediaFolder::options(
                    except: $record ? $record->selfAndDescendantIds() : [],
                ))
                ->live(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.media_folders.field.name'))
                    ->description(fn (MediaFolder $record): ?string => $record->parent_id ? $record->path() : null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('media_count')
                    ->label(__('admin.media_folders.field.files'))
                    ->counts('media')
                    ->sortable(),
                TextColumn::make('children_count')
                    ->label(__('admin.media_folders.field.folders'))
                    ->counts('children'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMediaFolders::route('/'),
        ];
    }

    /**
     * @return Builder<MediaFolder>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<MediaFolder> $query */
        $query = parent::getEloquentQuery();

        return $query->where('site_id', Site::current()->id);
    }
}
