<?php

namespace App\Filament\Resources\Activities\Tables;

use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class ActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('admin.activity.field.when'))
                    ->dateTime()
                    ->description(fn (Activity $record): string => (string) $record->created_at?->diffForHumans())
                    ->sortable(),
                TextColumn::make('log_name')
                    ->label(__('admin.activity.field.log'))
                    ->badge(),
                TextColumn::make('event')
                    ->label(__('admin.activity.field.event'))
                    ->searchable(),
                TextColumn::make('causer.name')
                    ->label(__('admin.activity.field.causer'))
                    ->placeholder(__('admin.activity.field.system')),
                TextColumn::make('subject')
                    ->label(__('admin.activity.field.subject'))
                    ->state(fn (Activity $record): ?string => $record->subject_type
                        ? class_basename($record->subject_type).' #'.$record->subject_id : null)
                    ->placeholder('—'),
                TextColumn::make('ip')
                    ->label(__('admin.activity.field.ip'))
                    ->state(fn (Activity $record): ?string => $record->getExtraProperty('ip'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('log_name')
                    ->label(__('admin.activity.field.log'))
                    ->options(fn (): array => Activity::query()->distinct()->orderBy('log_name')
                        ->pluck('log_name', 'log_name')->filter()->all()),
                SelectFilter::make('event')
                    ->label(__('admin.activity.field.event'))
                    ->options(fn (): array => Activity::query()->distinct()->orderBy('event')
                        ->pluck('event', 'event')->filter()->all()),
                SelectFilter::make('causer_id')
                    ->label(__('admin.activity.field.causer'))
                    ->searchable()
                    ->options(fn (): array => User::withTrashed()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->where('causer_type', (new User)->getMorphClass())->where('causer_id', $data['value'])
                        : $query),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')->label(__('admin.activity.field.from')),
                        DatePicker::make('until')->label(__('admin.activity.field.until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
