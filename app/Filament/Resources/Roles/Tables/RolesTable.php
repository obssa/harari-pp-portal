<?php

namespace App\Filament\Resources\Roles\Tables;

use App\Domain\Access\Models\Role;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->paginated(false)
            ->columns([
                TextColumn::make('label')
                    ->label(__('admin.roles.field.name'))
                    ->state(fn (Role $record): string => $record->label())
                    ->weight('medium'),
                TextColumn::make('name')
                    ->label(__('admin.roles.field.key'))
                    ->fontFamily('mono')
                    ->color('gray')
                    ->searchable(),
                IconColumn::make('system')
                    ->label(__('admin.roles.field.system'))
                    ->boolean()
                    ->state(fn (Role $record): bool => $record->isSystem()),
                TextColumn::make('users_count')
                    ->label(__('admin.roles.field.users'))
                    ->numeric(),
                TextColumn::make('permissions_count')
                    ->label(__('admin.roles.field.permissions'))
                    ->numeric(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
