<?php

namespace App\Filament\Resources\ApiKeys;

use App\Domain\Api\Actions\IssueApiKey;
use App\Domain\Api\Enums\ApiScope;
use App\Domain\Api\Models\ApiKey;
use App\Filament\Resources\ApiKeys\Pages\ManageApiKeys;
use App\Filament\Support\CalendarDate;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Keys for the public API (ADR-0056), managed by Super Admins with the other
 * integrations. A key is shown once when issued; afterwards only its first
 * characters. Keys are revoked, never deleted.
 */
class ApiKeyResource extends Resource
{
    protected static ?string $model = ApiKey::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?int $navigationSort = 61;

    public static function getModelLabel(): string
    {
        return __('admin.api_keys.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.api_keys.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.administration');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('admin.api_keys.field.name'))
                ->helperText(__('admin.api_keys.help.name'))
                ->required()
                ->maxLength(120),
            CheckboxList::make('scopes')
                ->label(__('admin.api_keys.field.scopes'))
                ->options(ApiScope::options())
                ->required(),
            TextInput::make('rate_per_minute')
                ->label(__('admin.api_keys.field.rate'))
                ->helperText(__('admin.api_keys.help.rate'))
                ->integer()
                ->minValue(1)
                ->maxValue((int) config('portal.api.max_rate'))
                ->default((int) config('portal.api.default_rate'))
                ->required(),
            DateTimePicker::make('expires_at')
                ->label(__('admin.api_keys.field.expires'))
                ->helperText(__('admin.api_keys.help.expires'))
                ->seconds(false)
                ->after('now'),
            Textarea::make('notes')
                ->label(__('admin.api_keys.field.notes'))
                ->maxLength(500)
                ->rows(2),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('name')->label(__('admin.api_keys.field.name'))->searchable(),
                TextColumn::make('prefix')
                    ->label(__('admin.api_keys.field.key'))
                    ->formatStateUsing(fn (string $state): string => $state.'…')
                    ->fontFamily('mono'),
                TextColumn::make('scopes')
                    ->label(__('admin.api_keys.field.scopes'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ApiScope::tryFrom($state)?->label() ?? $state),
                TextColumn::make('rate_per_minute')->label(__('admin.api_keys.field.rate')),
                TextColumn::make('status')
                    ->label(__('admin.api_keys.field.status'))
                    ->badge()
                    ->state(fn (ApiKey $record): string => match (true) {
                        $record->revoked_at !== null => 'revoked',
                        ! $record->isUsable() => 'expired',
                        default => 'active',
                    })
                    ->formatStateUsing(fn (string $state): string => __('admin.api_keys.status.'.$state))
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray'),
                TextColumn::make('last_used_at')
                    ->label(__('admin.api_keys.field.last_used'))
                    ->formatStateUsing(CalendarDate::format(withTime: true))
                    ->placeholder(__('admin.api_keys.never_used')),
                TextColumn::make('expires_at')
                    ->label(__('admin.api_keys.field.expires'))
                    ->formatStateUsing(CalendarDate::format(withTime: true))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(fn (ApiKey $record, array $data): ApiKey => app(IssueApiKey::class)->update($record, $data)),
                Action::make('revoke')
                    ->label(__('admin.api_keys.action.revoke'))
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('admin.api_keys.revoke_confirm'))
                    ->visible(fn (ApiKey $record): bool => Auth::user()?->can('revoke', $record) ?? false)
                    ->action(function (ApiKey $record): void {
                        app(IssueApiKey::class)->revoke($record);
                        Notification::make()->success()->title(__('admin.api_keys.revoked'))->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageApiKeys::route('/'),
        ];
    }
}
