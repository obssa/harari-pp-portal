<?php

namespace App\Filament\Resources\SocialSources;

use App\Domain\Organisation\Models\League;
use App\Domain\Organisation\Models\Office;
use App\Domain\Social\Actions\FetchSocialSource;
use App\Domain\Social\Enums\Platform;
use App\Domain\Social\Models\SocialSource;
use App\Domain\Social\Support\Fetchers;
use App\Filament\Resources\SocialSources\Pages\ManageSocialSources;
use App\Filament\Support\CalendarDate;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The accounts the social hub reads (ADR-0031).
 */
class SocialSourceResource extends Resource
{
    protected static ?string $model = SocialSource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?int $navigationSort = 40;

    public static function getModelLabel(): string
    {
        return __('social.source.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('social.source.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('social.admin.nav');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('platform')->label(__('social.source.field.platform'))->options(Platform::options())->required()->live(),
            TextInput::make('handle')->label(__('social.source.field.handle'))->required()->maxLength(160)
                ->helperText(fn (Get $get): string => match (Platform::tryFrom((string) $get('platform'))) {
                    Platform::YouTube => __('social.source.help.handle_youtube'),
                    Platform::Facebook => __('social.source.help.handle_facebook'),
                    Platform::TikTok => __('social.source.help.handle_tiktok'),
                    Platform::Instagram => __('social.source.help.handle_instagram'),
                    Platform::X => __('social.source.help.handle_x'),
                    default => __('social.source.help.handle_telegram'),
                })
                ->rules([fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                    $platform = Platform::tryFrom((string) $get('platform'));
                    if ($platform !== null && preg_match($platform->handlePattern(), (string) $value) !== 1) {
                        $fail(__('social.source.validation.handle'));
                    }
                }]),
            TextInput::make('name')->label(__('social.source.field.name'))->required()->maxLength(160),
            Select::make('office_id')->label(__('admin.leaders.field.office'))->searchable()
                ->options(fn (): array => Office::query()->with('translations')->orderBy('sort')->get()->mapWithKeys(fn (Office $o): array => [$o->id => $o->name()])->all()),
            Select::make('league_id')->label(__('admin.leaders.field.league'))
                ->options(fn (): array => League::query()->with('translations')->orderBy('sort')->get()->mapWithKeys(fn (League $l): array => [$l->id => $l->name()])->all()),
            TextInput::make('interval_minutes')->label(__('social.source.field.interval'))->numeric()->integer()->minValue(15)->maxValue(1440)->default(60)->suffix(__('social.source.minutes')),
            Toggle::make('enabled')->label(__('social.source.field.enabled'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('posts'))
            ->columns([
                TextColumn::make('name')->label(__('social.source.field.name'))
                    ->description(fn (SocialSource $record): string => $record->url())
                    ->url(fn (SocialSource $record): string => $record->url(), shouldOpenInNewTab: true),
                TextColumn::make('platform')->label(__('social.source.field.platform'))->badge()
                    ->formatStateUsing(fn (Platform $state): string => $state->label()),
                IconColumn::make('enabled')->label(__('social.source.field.enabled'))->boolean(),
                TextColumn::make('posts_count')->label(__('social.source.field.posts'))->numeric(),
                TextColumn::make('last_fetched_at')->label(__('import.source.field.last_fetched_at'))
                    ->formatStateUsing(CalendarDate::format(withTime: true))->placeholder('—'),
                TextColumn::make('last_error')->label(__('import.source.field.last_error'))->limit(60)->color('danger')
                    ->state(fn (SocialSource $record): ?string => app(Fetchers::class)->supports($record->platform) ? $record->last_error : __('social.source.not_read_yet'))
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('fetch')
                    ->label(__('social.source.fetch_now'))
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->authorize('update')
                    ->visible(fn (SocialSource $record): bool => app(Fetchers::class)->supports($record->platform))
                    ->action(function (SocialSource $record): void {
                        $result = app(FetchSocialSource::class)->handle($record);
                        $notification = Notification::make()->title(__('social.source.fetched', ['added' => $result['added'], 'updated' => $result['updated']]));
                        $result['error'] !== null ? $notification->danger()->body($result['error'])->send() : $notification->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSocialSources::route('/')];
    }
}
