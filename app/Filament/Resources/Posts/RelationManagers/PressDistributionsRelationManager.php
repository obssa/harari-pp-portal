<?php

namespace App\Filament\Resources\Posts\RelationManagers;

use App\Domain\Content\Models\Post;
use App\Domain\Press\Actions\DistributePressRelease;
use App\Domain\Press\Models\PressDistribution;
use App\Domain\Translation\Support\Locales;
use App\Filament\Support\CalendarDate;
use App\Filament\Support\CurrentUser;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Where a press release went (ADR-0054): each media contact, the language,
 * what happened and who sent it.
 */
class PressDistributionsRelationManager extends RelationManager
{
    protected static string $relationship = 'pressDistributions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('press.distribution.title');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Post && in_array($ownerRecord->type, DistributePressRelease::TYPES, true)
            && CurrentUser::get()->can('media_contacts.manage');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['contact', 'sender']))
            ->columns([
                TextColumn::make('outlet')->label(__('press.media.outlet'))->state(fn (PressDistribution $record): string => $record->contact->outlet),
                TextColumn::make('contact_name')->label(__('membership.field.name'))->state(fn (PressDistribution $record): string => $record->contact->name),
                TextColumn::make('locale')->label(__('newsletter.field.language'))->formatStateUsing(fn (string $state): string => Locales::nativeName($state)),
                TextColumn::make('status')->label(__('membership.admin.status'))->badge()
                    ->formatStateUsing(fn (string $state): string => __("campaigns.report.status.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        PressDistribution::SENT => 'success',
                        PressDistribution::FAILED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('sent_at')->label(__('campaigns.report.column.sent_at'))->formatStateUsing(CalendarDate::format(withTime: true))->placeholder('—'),
                TextColumn::make('sender.name')->label(__('campaigns.field.sent_by'))->placeholder('—'),
            ])
            ->emptyStateHeading(__('press.distribution.empty'));
    }
}
