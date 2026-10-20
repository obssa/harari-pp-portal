<?php

namespace App\Filament\Resources\TranslationImports;

use App\Domain\Translation\Actions\ImportTranslations;
use App\Domain\Translation\Models\TranslationImport;
use App\Domain\Translation\Support\Locales;
use App\Filament\Resources\TranslationImports\Pages\ListTranslationImports;
use App\Filament\Support\CalendarDate;
use App\Filament\Support\CurrentUser;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Every translation file brought in (ADR-0033), with rollback.
 */
class TranslationImportResource extends Resource
{
    protected static ?string $model = TranslationImport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?int $navigationSort = 51;

    protected static ?string $slug = 'translation-imports';

    public static function getModelLabel(): string
    {
        return __('translations.import.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('translations.import.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('translations.admin.nav');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->columns([
                TextColumn::make('created_at')->label(__('translations.import.date'))->formatStateUsing(CalendarDate::format(withTime: true)),
                TextColumn::make('locale')->label(__('admin.revisions.field.language'))->formatStateUsing(fn (string $state): string => Locales::nativeName($state)),
                TextColumn::make('file_name')->label(__('translations.import.file'))->wrap(),
                TextColumn::make('status')->label(__('translations.admin.status'))->badge()
                    ->formatStateUsing(fn (string $state): string => __("translations.import.status.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        TranslationImport::APPLIED => 'success',
                        TranslationImport::PENDING => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('added')->label(__('translations.import.added'))->numeric(),
                TextColumn::make('changed')->label(__('translations.import.changed'))->numeric(),
                TextColumn::make('problems_count')->label(__('translations.import.problems'))
                    ->state(fn (TranslationImport $record): int => count($record->problems ?? []))->numeric(),
                TextColumn::make('user.name')->label(__('admin.revisions.field.author'))->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('locale')->label(__('admin.revisions.field.language'))
                    ->options(collect(Locales::all())->mapWithKeys(fn (string $l): array => [$l => Locales::nativeName($l)])->all()),
            ])
            ->recordActions([
                Action::make('rollback')
                    ->label(__('translations.import.rollback'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('translations.import.rollback_help'))
                    ->authorize('update')
                    ->visible(fn (TranslationImport $record): bool => $record->status === TranslationImport::APPLIED)
                    ->action(function (TranslationImport $record): void {
                        app(ImportTranslations::class)->rollback($record, CurrentUser::get());
                        Notification::make()->success()->title(__('translations.import.rolled_back'))->send();
                    }),
                Action::make('discard')
                    ->label(__('translations.import.discard'))
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('gray')
                    ->authorize('update')
                    ->visible(fn (TranslationImport $record): bool => $record->status === TranslationImport::PENDING)
                    ->action(fn (TranslationImport $record) => app(ImportTranslations::class)->discard($record)),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListTranslationImports::route('/')];
    }
}
