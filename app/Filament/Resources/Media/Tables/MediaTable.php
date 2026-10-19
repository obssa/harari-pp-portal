<?php

namespace App\Filament\Resources\Media\Tables;

use App\Domain\Media\Actions\DeleteMedia;
use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Exceptions\MediaInUse;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Models\MediaFolder;
use App\Filament\Support\CalendarDate;
use App\Filament\Support\CurrentUser;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Number;

class MediaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('preview')
                    ->label(__('admin.media.field.preview'))
                    ->state(fn (Media $record): ?string => $record->adminPreviewUrl())
                    ->imageSize(64)
                    ->extraImgAttributes(fn (Media $record): array => ['alt' => $record->altFor(), 'loading' => 'lazy']),
                TextColumn::make('name')
                    ->label(__('admin.media.field.name'))
                    ->description(fn (Media $record): string => $record->file_name)
                    ->searchable(['name', 'file_name'])
                    ->sortable()
                    ->wrap(),
                TextColumn::make('kind')
                    ->label(__('admin.media.field.kind'))
                    ->badge()
                    ->formatStateUsing(fn (MediaKind $state): string => $state->label()),
                TextColumn::make('folder.name')
                    ->label(__('admin.media.field.folder'))
                    ->placeholder(__('admin.media.folder.none'))
                    ->toggleable(),
                IconColumn::make('alt_complete')
                    ->label(__('admin.media.field.alt_complete'))
                    ->state(fn (Media $record): ?bool => $record->isImage()
                        ? $record->hasAltFor('en') && $record->hasAltFor('am') && $record->hasAltFor('om')
                        : null)
                    ->boolean()
                    ->tooltip(fn (Media $record): ?string => $record->isImage()
                        ? __('admin.media.alt_status', ['locales' => self::altLocales($record)])
                        : null),
                TextColumn::make('size')
                    ->label(__('admin.media.field.size'))
                    ->formatStateUsing(fn (int $state): string => Number::fileSize($state, precision: 1))
                    ->sortable(),
                TextColumn::make('dimensions')
                    ->label(__('admin.media.field.dimensions'))
                    ->state(fn (Media $record): ?string => $record->width ? "{$record->width} × {$record->height}" : null)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('usages_count')
                    ->label(__('admin.media.field.used'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('uploader.name')
                    ->label(__('admin.media.field.uploaded_by'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('admin.media.field.uploaded_at'))
                    ->formatStateUsing(CalendarDate::format())
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('kind')
                    ->label(__('admin.media.field.kind'))
                    ->options(collect(MediaKind::cases())
                        ->mapWithKeys(fn (MediaKind $kind): array => [$kind->value => $kind->label()])->all()),
                SelectFilter::make('folder_id')
                    ->label(__('admin.media.field.folder'))
                    ->options(fn (): array => MediaFolder::options()),
                Filter::make('missing_alt')
                    ->label(__('admin.media.filter.missing_alt'))
                    ->query(fn (Builder $query): Builder => $query->whereKey(Media::query()->missingAlt()->select('id'))),
                Filter::make('unused')
                    ->label(__('admin.media.filter.unused'))
                    ->query(fn (Builder $query): Builder => $query->whereKey(Media::query()->unused()->select('id'))),
                Filter::make('mine')
                    ->label(__('admin.media.filter.mine'))
                    ->query(fn (Builder $query): Builder => $query->where('uploaded_by', CurrentUser::get()->id)),
                SelectFilter::make('tag')
                    ->label(__('admin.media.field.tags'))
                    ->options(fn (): array => self::tagOptions())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereJsonContains('tags', $data['value'])
                        : $query),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->using(function (Media $record): bool {
                        app(DeleteMedia::class)->handle($record);

                        return true;
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::moveToFolder(),
                    self::addTags(),
                    self::deleteUnused(),
                ]),
            ]);
    }

    private static function moveToFolder(): BulkAction
    {
        return BulkAction::make('move')
            ->label(__('admin.media.action.move'))
            ->icon(Heroicon::OutlinedFolderArrowDown)
            ->authorize(fn (): bool => CurrentUser::get()->can('media.update'))
            ->schema([
                Select::make('folder_id')
                    ->label(__('admin.media.field.folder'))
                    ->options(fn (): array => MediaFolder::options())
                    ->placeholder(__('admin.media.folder.none')),
            ])
            ->action(function (Collection $records, array $data): void {
                $folderId = filled($data['folder_id'] ?? null) ? (int) $data['folder_id'] : null;
                foreach (self::media($records) as $media) {
                    $media->update(['folder_id' => $folderId]);
                }

                Notification::make()->success()->title(__('admin.media.moved', ['count' => $records->count()]))->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    private static function addTags(): BulkAction
    {
        return BulkAction::make('tag')
            ->label(__('admin.media.action.tag'))
            ->icon(Heroicon::OutlinedTag)
            ->authorize(fn (): bool => CurrentUser::get()->can('media.update'))
            ->schema([
                TagsInput::make('tags')->label(__('admin.media.field.tags'))->required(),
            ])
            ->action(function (Collection $records, array $data): void {
                $new = self::cleanTags((array) ($data['tags'] ?? []));
                foreach (self::media($records) as $media) {
                    $media->update(['tags' => array_values(array_unique([...($media->tags ?? []), ...$new]))]);
                }

                Notification::make()->success()->title(__('admin.media.tagged', ['count' => $records->count()]))->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    private static function deleteUnused(): BulkAction
    {
        return BulkAction::make('delete')
            ->label(__('admin.media.action.delete_unused'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->authorize(fn (): bool => CurrentUser::get()->can('media.delete'))
            ->action(function (Collection $records): void {
                $deleted = 0;
                $kept = 0;
                foreach (self::media($records) as $media) {
                    try {
                        app(DeleteMedia::class)->handle($media);
                        $deleted++;
                    } catch (MediaInUse) {
                        $kept++;
                    }
                }

                Notification::make()
                    ->title(__('admin.media.deleted', ['count' => $deleted]))
                    ->body($kept > 0 ? __('admin.media.kept_in_use', ['count' => $kept]) : null)
                    ->status($kept > 0 ? 'warning' : 'success')
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * @param  Collection<int, covariant \Illuminate\Database\Eloquent\Model>  $records
     * @return list<Media>
     */
    private static function media(Collection $records): array
    {
        return $records->filter(fn (mixed $record): bool => $record instanceof Media)->values()->all();
    }

    /**
     * @param  array<mixed>  $tags
     * @return list<string>
     */
    public static function cleanTags(array $tags): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (mixed $tag): string => mb_substr(trim((string) $tag), 0, 40),
            $tags,
        ))));
    }

    /**
     * @return array<string, string>
     */
    private static function tagOptions(): array
    {
        return Media::query()->library()->whereNotNull('tags')->pluck('tags')
            ->flatten()->filter()->unique()->sort()->mapWithKeys(fn (string $tag): array => [$tag => $tag])->all();
    }

    private static function altLocales(Media $record): string
    {
        return collect(['en', 'am', 'om', 'har'])
            ->map(fn (string $locale): string => strtoupper($locale).($record->hasAltFor($locale) ? ' ✓' : ' —'))
            ->implode(' · ');
    }
}
