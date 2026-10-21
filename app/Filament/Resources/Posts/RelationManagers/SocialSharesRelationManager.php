<?php

namespace App\Filament\Resources\Posts\RelationManagers;

use App\Domain\Social\Models\SocialShare;
use App\Domain\Translation\Support\Locales;
use App\Filament\Support\CalendarDate;
use App\Filament\Support\CurrentUser;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Where a post was shared (ADR-0059): each Facebook page or Telegram
 * channel, the language, what happened, the link to the shared post and
 * who shared it.
 */
class SocialSharesRelationManager extends RelationManager
{
    protected static string $relationship = 'socialShares';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('social.share.title');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return CurrentUser::get()->can('social.share') || CurrentUser::get()->can('social.manage');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('sharer'))
            ->columns([
                TextColumn::make('platform')->label(__('social.share.column.platform'))
                    ->formatStateUsing(fn (SocialShare $record): string => $record->platform->label()),
                TextColumn::make('account')->label(__('social.share.column.account')),
                TextColumn::make('locale')->label(__('newsletter.field.language'))->formatStateUsing(fn (string $state): string => Locales::nativeName($state)),
                TextColumn::make('status')->label(__('membership.admin.status'))->badge()
                    ->formatStateUsing(fn (string $state): string => __("social.share.status.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        SocialShare::SENT => 'success',
                        SocialShare::FAILED => 'danger',
                        default => 'gray',
                    })
                    ->description(fn (SocialShare $record): ?string => $record->status === SocialShare::FAILED ? $record->error : null),
                TextColumn::make('external_url')->label(__('social.share.column.link'))
                    ->formatStateUsing(fn (): string => __('social.share.open'))
                    ->url(fn (SocialShare $record): ?string => $record->external_url, shouldOpenInNewTab: true)
                    ->placeholder('—'),
                TextColumn::make('shared_at')->label(__('social.share.column.shared_at'))->formatStateUsing(CalendarDate::format(withTime: true))->placeholder('—'),
                TextColumn::make('sharer.name')->label(__('social.share.column.shared_by'))->placeholder('—'),
            ])
            ->emptyStateHeading(__('social.share.empty'));
    }
}
