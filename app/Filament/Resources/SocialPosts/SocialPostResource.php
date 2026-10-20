<?php

namespace App\Filament\Resources\SocialPosts;

use App\Domain\Social\Enums\Platform;
use App\Domain\Social\Models\SocialPost;
use App\Domain\Social\Models\SocialSource;
use App\Filament\Resources\SocialPosts\Pages\ListSocialPosts;
use App\Filament\Support\CalendarDate;
use App\Filament\Support\CurrentUser;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Moderation of the posts the hub shows (ADR-0031): hide one, or pin it to
 * the top. The text stays as the account published it.
 */
class SocialPostResource extends Resource
{
    protected static ?string $model = SocialPost::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleOvalLeftEllipsis;

    protected static ?int $navigationSort = 41;

    public static function getModelLabel(): string
    {
        return __('social.post.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('social.post.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('social.admin.nav');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('posted_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['source', 'image']))
            ->columns([
                ImageColumn::make('picture')->label('')
                    ->state(fn (SocialPost $record): ?string => $record->image?->adminPreviewUrl())->square()->imageSize(48),
                TextColumn::make('text')->label(__('social.post.field.text'))->limit(120)->wrap()->placeholder('—')
                    ->url(fn (SocialPost $record): string => $record->permalink, shouldOpenInNewTab: true),
                TextColumn::make('source.name')->label(__('social.source.singular'))
                    ->description(fn (SocialPost $record): string => $record->source->platform->label()),
                TextColumn::make('posted_at')->label(__('social.post.field.posted_at'))->formatStateUsing(CalendarDate::format(withTime: true))->sortable(),
                IconColumn::make('is_pinned')->label(__('social.post.field.pinned'))->boolean(),
                IconColumn::make('is_hidden')->label(__('social.post.field.hidden'))->boolean(),
            ])
            ->filters([
                SelectFilter::make('social_source_id')->label(__('social.source.singular'))
                    ->options(fn (): array => SocialSource::query()->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('platform')->label(__('social.source.field.platform'))->options(Platform::options())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('source', fn (Builder $s) => $s->where('platform', $data['value']))
                        : $query),
                TernaryFilter::make('is_hidden')->label(__('social.post.field.hidden')),
            ])
            ->recordActions([
                Action::make('pin')
                    ->label(fn (SocialPost $record): string => $record->is_pinned ? __('social.post.unpin') : __('social.post.pin'))
                    ->icon(Heroicon::OutlinedBookmark)
                    ->authorize('update')
                    ->action(function (SocialPost $record): void {
                        abort_unless(CurrentUser::get()->can('update', $record), 403);
                        $record->update(['is_pinned' => ! $record->is_pinned]);
                    }),
                Action::make('hide')
                    ->label(fn (SocialPost $record): string => $record->is_hidden ? __('social.post.show') : __('social.post.hide'))
                    ->icon(fn (SocialPost $record) => $record->is_hidden ? Heroicon::OutlinedEye : Heroicon::OutlinedEyeSlash)
                    ->color(fn (SocialPost $record): string => $record->is_hidden ? 'gray' : 'danger')
                    ->authorize('update')
                    ->action(function (SocialPost $record): void {
                        abort_unless(CurrentUser::get()->can('update', $record), 403);
                        $record->update(['is_hidden' => ! $record->is_hidden]);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListSocialPosts::route('/')];
    }
}
