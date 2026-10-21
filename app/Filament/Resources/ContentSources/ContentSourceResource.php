<?php

namespace App\Filament\Resources\ContentSources;

use App\Domain\Import\Actions\RunImport;
use App\Domain\Import\Models\ContentImport;
use App\Domain\Import\Models\ContentSource;
use App\Domain\Translation\Support\Locales;
use App\Filament\Resources\ContentSources\Pages\ManageContentSources;
use App\Filament\Support\CalendarDate;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The public channels stories are imported from (docs/content/sources.md,
 * ADR-0022): switch them on or off and import now.
 */
class ContentSourceResource extends Resource
{
    protected static ?string $model = ContentSource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static ?int $navigationSort = 44;

    public static function getModelLabel(): string
    {
        return __('import.source.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('import.source.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.administration');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('import.source.field.name'))->helperText(__('import.source.help.name'))->required()->maxLength(160),
            Select::make('default_locale')->label(__('import.source.field.default_locale'))
                ->helperText(__('import.source.help.default_locale'))
                ->options(collect(Locales::all())->mapWithKeys(fn (string $l): array => [$l => Locales::nativeName($l)])->all())
                ->required(),
            Toggle::make('enabled')->label(__('import.source.field.enabled')),
            Toggle::make('auto_publish')->label(__('import.source.field.auto_publish'))->helperText(__('import.source.help.auto_publish')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('priority')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'imports as imported_count' => fn (Builder $q) => $q->where('status', ContentImport::IMPORTED),
            ]))
            ->columns([
                TextColumn::make('name')->label(__('import.source.field.name'))
                    ->description(fn (ContentSource $record): string => $record->url())
                    ->url(fn (ContentSource $record): string => $record->url(), shouldOpenInNewTab: true),
                TextColumn::make('priority')->label(__('import.source.field.priority'))
                    ->formatStateUsing(fn (int $state): string => $state === 1 ? __('import.source.primary') : __('import.source.secondary'))
                    ->badge(),
                IconColumn::make('enabled')->label(__('import.source.field.enabled'))->boolean(),
                IconColumn::make('auto_publish')->label(__('import.source.field.auto_publish'))->boolean(),
                TextColumn::make('imported_count')->label(__('import.source.field.imported'))->numeric(),
                TextColumn::make('last_fetched_at')->label(__('import.source.field.last_fetched_at'))
                    ->formatStateUsing(CalendarDate::format(withTime: true))->placeholder('—'),
                TextColumn::make('last_error')->label(__('import.source.field.last_error'))->limit(60)->color('danger')->placeholder('—'),
            ])
            ->recordActions([
                Action::make('import')
                    ->label(__('import.source.import_now'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->authorize('import')
                    ->requiresConfirmation()
                    ->modalDescription(__('import.source.import_help'))
                    ->action(function (ContentSource $record): void {
                        $result = app(RunImport::class)->handle($record)[$record->handle] ?? null;
                        $notification = Notification::make()->title(__('import.source.import_done', [
                            'imported' => $result['imported'] ?? 0,
                            'duplicates' => $result['duplicates'] ?? 0,
                        ]));
                        filled($result['error'] ?? null)
                            ? $notification->danger()->body((string) $result['error'])->send()
                            : $notification->success()->send();
                    }),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageContentSources::route('/'),
        ];
    }
}
