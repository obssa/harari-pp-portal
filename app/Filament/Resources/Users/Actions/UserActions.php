<?php

namespace App\Filament\Resources\Users\Actions;

use App\Domain\Access\Actions\ManageAccount;
use App\Domain\Access\Enums\UserStatus;
use App\Filament\Support\CurrentUser;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Account actions shared by the users table and the edit page. Each one is
 * hidden unless UserPolicy allows it and re-checks authorization on submit.
 */
final class UserActions
{
    public static function sendSignInLink(): Action
    {
        return Action::make('sendSignInLink')
            ->label(__('admin.users.action.send_sign_in_link'))
            ->icon(Heroicon::OutlinedEnvelope)
            ->requiresConfirmation()
            ->modalDescription(__('admin.users.action.send_sign_in_link_help'))
            ->visible(fn (User $record): bool => ! $record->trashed() && self::actor()->can('update', $record))
            ->authorize(fn (User $record): bool => self::actor()->can('update', $record))
            ->action(function (User $record): void {
                app(ManageAccount::class)->sendSignInLink($record);
                Notification::make()->success()->title(__('admin.users.action.sign_in_link_sent'))->send();
            });
    }

    public static function suspend(): Action
    {
        return Action::make('suspend')
            ->label(__('admin.users.action.suspend'))
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('admin.users.action.suspend_help'))
            ->visible(fn (User $record): bool => $record->status === UserStatus::Active
                && ! $record->trashed() && $record->isNot(self::actor()) && self::actor()->can('update', $record))
            ->authorize(fn (User $record): bool => $record->isNot(self::actor()) && self::actor()->can('update', $record))
            ->action(function (User $record): void {
                app(ManageAccount::class)->suspend($record);
                Notification::make()->success()->title(__('admin.users.action.suspended'))->send();
            });
    }

    public static function reactivate(): Action
    {
        return Action::make('reactivate')
            ->label(__('admin.users.action.reactivate'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (User $record): bool => $record->status === UserStatus::Suspended
                && ! $record->trashed() && self::actor()->can('update', $record))
            ->authorize(fn (User $record): bool => self::actor()->can('update', $record))
            ->action(function (User $record): void {
                app(ManageAccount::class)->reactivate($record);
                Notification::make()->success()->title(__('admin.users.action.reactivated'))->send();
            });
    }

    public static function resetMfa(): Action
    {
        return Action::make('resetMfa')
            ->label(__('admin.users.action.reset_mfa'))
            ->icon(Heroicon::OutlinedKey)
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription(__('admin.users.action.reset_mfa_help'))
            ->visible(fn (User $record): bool => filled($record->getAppAuthenticationSecret())
                && self::actor()->can('resetMfa', $record))
            ->authorize(fn (User $record): bool => self::actor()->can('resetMfa', $record))
            ->action(function (User $record): void {
                app(ManageAccount::class)->resetMfa($record);
                Notification::make()->success()->title(__('admin.users.action.mfa_reset'))->send();
            });
    }

    private static function actor(): User
    {
        return CurrentUser::get();
    }
}
