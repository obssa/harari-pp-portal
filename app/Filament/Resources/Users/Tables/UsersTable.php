<?php

namespace App\Filament\Resources\Users\Tables;

use App\Domain\Access\Enums\UserStatus;
use App\Domain\Access\Models\Role;
use App\Filament\Resources\Users\Actions\UserActions;
use App\Models\User;
use App\Services\Calendar\EthiopianCalendar;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.users.field.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('admin.users.field.email'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('roles')
                    ->label(__('admin.users.field.roles'))
                    ->badge()
                    ->state(fn (User $record): array => $record->roles
                        ->map(fn (mixed $r): string => $r instanceof Role ? $r->label() : '')->filter()->values()->all()),
                TextColumn::make('status')
                    ->label(__('admin.users.field.status'))
                    ->badge()
                    ->formatStateUsing(fn (UserStatus $state): string => $state->label())
                    ->color(fn (UserStatus $state): string => $state === UserStatus::Active ? 'success' : 'danger'),
                IconColumn::make('mfa')
                    ->label(__('admin.users.field.mfa'))
                    ->boolean()
                    ->state(fn (User $record): bool => filled($record->getAppAuthenticationSecret())),
                TextColumn::make('last_login_at')
                    ->label(__('admin.users.field.last_login'))
                    ->since()
                    ->tooltip(fn (User $record): ?string => $record->last_login_at
                        ? app(EthiopianCalendar::class)->format($record->last_login_at, withTime: true)
                        : null)
                    ->placeholder(__('admin.users.field.never'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label(__('admin.users.field.roles'))
                    ->options(fn (): array => Role::query()->orderBy('id')->get()
                        ->mapWithKeys(fn (Role $r): array => [$r->name => $r->label()])->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('roles', fn (Builder $q) => $q->where('name', $data['value']))
                        : $query),
                SelectFilter::make('status')
                    ->label(__('admin.users.field.status'))
                    ->options(collect(UserStatus::cases())
                        ->mapWithKeys(fn (UserStatus $s): array => [$s->value => $s->label()])->all()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    UserActions::sendSignInLink(),
                    UserActions::suspend(),
                    UserActions::reactivate(),
                    UserActions::resetMfa(),
                    DeleteAction::make(),
                    RestoreAction::make(),
                ]),
            ]);
    }
}
