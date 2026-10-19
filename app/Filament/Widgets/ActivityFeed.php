<?php

namespace App\Filament\Widgets;

use App\Domain\Dashboard\Contracts\DashboardWidget;
use App\Filament\Support\CalendarDate;
use App\Filament\Widgets\Concerns\IsDashboardWidget;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Spatie\Activitylog\Models\Activity;

/**
 * Who did what, most recent first (PLAN.md §7 activity feed).
 */
class ActivityFeed extends TableWidget implements DashboardWidget
{
    use IsDashboardWidget;

    public static function key(): string
    {
        return 'activity';
    }

    public static function title(): string
    {
        return __('dashboard.widget.activity');
    }

    public static function permission(): ?string
    {
        return 'activity.view';
    }

    public static function defaultSize(): int
    {
        return 12;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(self::title())
            ->query(fn () => Activity::query()->with('causer')->latest('id'))
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10])
            ->columns([
                TextColumn::make('created_at')->label(__('dashboard.activity.when'))
                    ->formatStateUsing(CalendarDate::format(withTime: true)),
                TextColumn::make('causer.name')->label(__('dashboard.activity.who'))->placeholder(__('dashboard.activity.system')),
                TextColumn::make('description')->label(__('dashboard.activity.what'))
                    ->formatStateUsing(fn (Activity $record): string => trim(($record->event ?? $record->description).' · '.$record->log_name, ' ·')),
                TextColumn::make('subject_type')->label(__('dashboard.activity.record'))
                    ->formatStateUsing(fn (?string $state): string => $state !== null ? class_basename($state) : ''),
            ]);
    }
}
