<?php

namespace App\Filament\Pages;

use App\Domain\Notifications\Enums\NoticeKind;
use App\Domain\Notifications\Support\NoticePreferences;
use App\Filament\Support\CalendarDate;
use App\Filament\Support\CurrentUser;
use App\Models\User;
use App\Services\Telegram\TelegramBot;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

/**
 * Each person's own notices in one list (PLAN.md §7, ADR-0036): filter what
 * is unread, open, mark as read or unread, delete; and choose in-app or
 * email per kind of notice. The bell shows the same notices.
 */
class NotificationCentre extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    protected static ?string $slug = 'notifications';

    protected static ?int $navigationSort = -1;

    public static function getNavigationLabel(): string
    {
        return __('notifications.title');
    }

    public function getTitle(): string
    {
        return __('notifications.title');
    }

    public static function canAccess(): bool
    {
        return Filament::auth()->user() instanceof User;
    }

    public static function getNavigationBadge(): ?string
    {
        $user = Filament::auth()->user();
        $count = $user instanceof User ? $user->unreadNotifications()->count() : 0;

        return $count > 0 ? (string) $count : null;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('markAllRead')
                ->label(__('notifications.action.mark_all_read'))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('gray')
                ->visible(fn (): bool => CurrentUser::get()->unreadNotifications()->exists())
                ->action(function (): void {
                    CurrentUser::get()->unreadNotifications()->update(['read_at' => now()]);
                    $this->resetTable();
                }),
            Action::make('telegram')
                ->label(fn (): string => CurrentUser::get()->telegram_chat_id === null ? __('notifications.telegram.connect') : __('notifications.telegram.disconnect'))
                ->icon(Heroicon::OutlinedChatBubbleOvalLeftEllipsis)
                ->color('gray')
                ->visible(fn (): bool => app(TelegramBot::class)->isConfigured())
                ->modalSubmitAction(fn (Action $action): Action => $action->label(CurrentUser::get()->telegram_chat_id === null ? __('notifications.telegram.done') : __('notifications.telegram.disconnect')))
                ->modalDescription(fn (): string => CurrentUser::get()->telegram_chat_id === null ? __('notifications.telegram.connect_help') : __('notifications.telegram.disconnect_help'))
                ->schema(fn (): array => CurrentUser::get()->telegram_chat_id !== null ? [] : [
                    TextEntry::make('link')->label(__('notifications.telegram.link'))
                        ->state(fn (): ?string => $this->telegramLink())->url(fn (): ?string => $this->telegramLink(), true)->copyable(),
                ])
                ->action(function (): void {
                    $user = CurrentUser::get();
                    if ($user->telegram_chat_id !== null) {
                        $user->forceFill(['telegram_chat_id' => null, 'telegram_linked_at' => null])->save();
                        activity('notifications')->performedOn($user)->causedBy($user)->event('telegram_unlinked')->log('Telegram unlinked');
                    }
                }),
            Action::make('preferences')
                ->label(__('notifications.action.preferences'))
                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                ->modalDescription(__('notifications.preferences.help'))
                ->fillForm(fn (): array => NoticePreferences::all(CurrentUser::get()))
                ->schema(fn (): array => array_map(
                    fn (NoticeKind $kind): CheckboxList => CheckboxList::make($kind->value)
                        ->label($kind->label())
                        ->helperText($kind->help())
                        ->options(array_filter([
                            NoticePreferences::IN_APP => __('notifications.channel.in_app'),
                            NoticePreferences::EMAIL => __('notifications.channel.email'),
                            NoticePreferences::TELEGRAM => CurrentUser::get()->telegram_chat_id !== null ? __('notifications.channel.telegram') : null,
                        ]))
                        ->columns(3),
                    NoticeKind::for(CurrentUser::get()),
                ))
                ->action(function (array $data): void {
                    NoticePreferences::save(CurrentUser::get(), $data);
                    Notification::make()->success()->title(__('notifications.preferences.saved'))->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => DatabaseNotification::query()
                ->where('notifiable_type', CurrentUser::get()->getMorphClass())
                ->where('notifiable_id', CurrentUser::get()->getKey()))
            ->defaultSort('created_at', 'desc')
            ->poll('60s')
            ->columns([
                TextColumn::make('data.title')
                    ->label(__('notifications.column.notice'))
                    ->icon(fn (DatabaseNotification $record): ?string => is_string($icon = data_get($record->data, 'icon')) ? $icon : null)
                    ->weight(fn (DatabaseNotification $record): FontWeight => $record->read_at === null ? FontWeight::Bold : FontWeight::Normal)
                    ->description(fn (DatabaseNotification $record): ?string => is_string($body = data_get($record->data, 'body')) ? Str::limit($body, 300) : null)
                    ->wrap(),
                TextColumn::make('data.kind')
                    ->label(__('notifications.column.kind'))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): string => NoticeKind::tryFrom((string) $state)?->label() ?? '—'),
                TextColumn::make('created_at')
                    ->label(__('notifications.column.received'))
                    ->formatStateUsing(CalendarDate::format(withTime: true))
                    ->description(fn (DatabaseNotification $record): ?string => $record->created_at?->diffForHumans())
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('read')
                    ->label(__('notifications.filter.state'))
                    ->placeholder(__('notifications.filter.all'))
                    ->trueLabel(__('notifications.filter.unread'))
                    ->falseLabel(__('notifications.filter.read'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNull('read_at'),
                        false: fn (Builder $query): Builder => $query->whereNotNull('read_at'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                SelectFilter::make('kind')
                    ->label(__('notifications.column.kind'))
                    ->options(collect(NoticeKind::cases())->mapWithKeys(fn (NoticeKind $kind): array => [$kind->value => $kind->label()])->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null) ? $query->where('data->kind', (string) $data['value']) : $query),
            ])
            ->recordAction('open')
            ->recordActions([
                Action::make('open')
                    ->label(__('notifications.action.open'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->visible(fn (DatabaseNotification $record): bool => self::target($record) !== null)
                    ->action(function (DatabaseNotification $record): void {
                        $record->markAsRead();
                        $this->redirect((string) self::target($record));
                    }),
                Action::make('markRead')
                    ->label(__('notifications.action.mark_read'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('gray')
                    ->visible(fn (DatabaseNotification $record): bool => $record->read_at === null)
                    ->action(fn (DatabaseNotification $record) => $record->markAsRead()),
                Action::make('markUnread')
                    ->label(__('notifications.action.mark_unread'))
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->color('gray')
                    ->visible(fn (DatabaseNotification $record): bool => $record->read_at !== null)
                    ->action(fn (DatabaseNotification $record) => $record->markAsUnread()),
                Action::make('delete')
                    ->label(__('notifications.action.delete'))
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (DatabaseNotification $record) => $record->delete()),
            ])
            ->toolbarActions([
                BulkAction::make('markRead')
                    ->label(__('notifications.action.mark_read'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->action(function (Collection $records): void {
                        /** @var Collection<int, DatabaseNotification> $records */
                        $records->each(fn (DatabaseNotification $record) => $record->markAsRead());
                    })
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('delete')
                    ->label(__('notifications.action.delete'))
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
                        /** @var Collection<int, DatabaseNotification> $records */
                        $records->each(fn (DatabaseNotification $record) => $record->delete());
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateIcon(Heroicon::OutlinedBellSlash)
            ->emptyStateHeading(__('notifications.empty'))
            ->emptyStateDescription(__('notifications.empty_help'));
    }

    /** The page a notice links to, only within the portal. */
    /** A deep link to the bot with a fresh code, valid for a day (ADR-0055). */
    private function telegramLink(): ?string
    {
        $user = CurrentUser::get();
        $username = app(TelegramBot::class)->username();
        if ($username === null) {
            return null;
        }
        if ($user->telegram_link_code === null || $user->telegram_link_expires_at?->isPast() !== false) {
            $user->forceFill(['telegram_link_code' => Str::random(10), 'telegram_link_expires_at' => now()->addDay()])->save();
        }

        return 'https://t.me/'.$username.'?start='.$user->telegram_link_code;
    }

    private static function target(DatabaseNotification $record): ?string
    {
        $url = data_get($record->data, 'actions.0.url');

        return is_string($url) && Str::startsWith($url, rtrim(url('/'), '/').'/') ? $url : null;
    }
}
