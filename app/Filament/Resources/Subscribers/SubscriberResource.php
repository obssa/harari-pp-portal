<?php

namespace App\Filament\Resources\Subscribers;

use App\Domain\Engagement\Models\Subscriber;
use App\Domain\Engagement\Support\SubscriptionOptions;
use App\Domain\Translation\Support\Locales;
use App\Filament\Resources\Subscribers\Pages\ListSubscribers;
use App\Filament\Resources\Subscribers\Pages\ViewSubscriber;
use App\Filament\Support\CalendarDate;
use App\Support\PhoneNumber;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Newsletter and SMS subscribers (PLAN.md §5, ADR-0051): contact details
 * masked in the list; who confirmed which channel, their topics and
 * language. Subscribers join and leave only by themselves.
 */
class SubscriberResource extends Resource
{
    protected static ?string $model = Subscriber::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelopeOpen;

    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        return __('newsletter.admin.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('newsletter.admin.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.communication');
    }

    /**
     * @return Builder<Subscriber>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Subscriber> $query */
        $query = parent::getEloquentQuery()->whereNull('erased_at');

        return $query;
    }

    public static function state(Subscriber $subscriber): string
    {
        return match (true) {
            $subscriber->unsubscribed_at !== null => 'unsubscribed',
            $subscriber->isConfirmed() => 'confirmed',
            default => 'pending',
        };
    }

    public static function table(Table $table): Table
    {
        $contains = fn (string $column): \Closure => fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
            ? $query->whereJsonContains($column, (string) $data['value']) : $query;

        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('masked_email')->label(__('newsletter.field.email'))
                    ->state(fn (Subscriber $record): string => $record->email !== null ? self::maskEmail($record->email) : '—'),
                TextColumn::make('masked_phone')->label(__('newsletter.field.phone'))->fontFamily('mono')
                    ->state(fn (Subscriber $record): string => $record->phone !== null ? PhoneNumber::mask($record->phone) : '—'),
                TextColumn::make('state')->label(__('membership.admin.status'))->badge()
                    ->state(fn (Subscriber $record): string => self::state($record))
                    ->formatStateUsing(fn (string $state): string => __("newsletter.state.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'confirmed' => 'success',
                        'pending' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('channels')->label(__('newsletter.field.channels'))->badge()
                    ->formatStateUsing(fn (string $state): string => SubscriptionOptions::channels()[$state] ?? $state),
                TextColumn::make('topics')->label(__('newsletter.field.topics'))->badge()->limitList(2)
                    ->formatStateUsing(fn (string $state): string => SubscriptionOptions::topics()[$state] ?? $state),
                TextColumn::make('locale')->label(__('newsletter.field.language'))->formatStateUsing(fn (string $state): string => Locales::nativeName($state)),
                TextColumn::make('created_at')->label(__('volunteers.admin.signed_up'))->formatStateUsing(CalendarDate::format())->sortable(),
            ])
            ->filters([
                SelectFilter::make('state')->label(__('membership.admin.status'))
                    ->options(['confirmed' => __('newsletter.state.confirmed'), 'pending' => __('newsletter.state.pending'), 'unsubscribed' => __('newsletter.state.unsubscribed')])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'confirmed' => $query->whereNull('unsubscribed_at')->where(fn (Builder $q) => $q->whereNotNull('email_confirmed_at')->orWhereNotNull('phone_confirmed_at')),
                        'pending' => $query->whereNull('unsubscribed_at')->whereNull('email_confirmed_at')->whereNull('phone_confirmed_at'),
                        'unsubscribed' => $query->whereNotNull('unsubscribed_at'),
                        default => $query,
                    }),
                SelectFilter::make('channel')->label(__('newsletter.field.channels'))->options(SubscriptionOptions::channels())->query($contains('channels')),
                SelectFilter::make('topic')->label(__('newsletter.field.topics'))->options(SubscriptionOptions::topics())->query($contains('topics')),
                SelectFilter::make('locale')->label(__('newsletter.field.language'))
                    ->options(collect(Locales::all())->mapWithKeys(fn (string $l): array => [$l => Locales::nativeName($l)])->all()),
            ])
            ->recordActions([ViewAction::make()])
            ->recordUrl(fn (Subscriber $record): string => self::getUrl('view', ['record' => $record]))
            ->emptyStateHeading(__('newsletter.admin.empty'));
    }

    public static function infolist(Schema $schema): Schema
    {
        $list = fn (array $labels): \Closure => fn (?array $state): string => $state === null || $state === []
            ? '—' : implode(', ', array_map(fn (string $key): string => $labels[$key] ?? $key, $state));

        return $schema->columns(2)->components([
            Section::make(__('newsletter.admin.contact'))->schema([
                TextEntry::make('email')->label(__('newsletter.field.email'))->copyable()->placeholder('—'),
                TextEntry::make('email_confirmed_at')->label(__('newsletter.admin.email_confirmed'))->formatStateUsing(CalendarDate::format(withTime: true))->placeholder('—'),
                TextEntry::make('phone')->label(__('newsletter.field.phone'))->copyable()->placeholder('—'),
                TextEntry::make('phone_confirmed_at')->label(__('newsletter.admin.phone_confirmed'))->formatStateUsing(CalendarDate::format(withTime: true))->placeholder('—'),
            ]),
            Section::make(__('newsletter.admin.choices'))->schema([
                TextEntry::make('state')->label(__('membership.admin.status'))->badge()
                    ->state(fn (Subscriber $record): string => __('newsletter.state.'.self::state($record))),
                TextEntry::make('channels')->label(__('newsletter.field.channels'))->state(fn (Subscriber $record): string => $list(SubscriptionOptions::channels())($record->channels)),
                TextEntry::make('topics')->label(__('newsletter.field.topics'))->state(fn (Subscriber $record): string => $list(SubscriptionOptions::topics())($record->topics)),
                TextEntry::make('locale')->label(__('newsletter.field.language'))->formatStateUsing(fn (string $state): string => Locales::nativeName($state)),
                TextEntry::make('created_at')->label(__('volunteers.admin.signed_up'))->formatStateUsing(CalendarDate::format(withTime: true)),
                TextEntry::make('unsubscribed_at')->label(__('newsletter.admin.unsubscribed_at'))->formatStateUsing(CalendarDate::format(withTime: true))->placeholder('—'),
            ]),
        ]);
    }

    /** "s•••@example.org": enough to recognise, not to copy. */
    public static function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return $name === '' ? '—' : mb_substr($name, 0, 1).'•••@'.$domain;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscribers::route('/'),
            'view' => ViewSubscriber::route('/{record}'),
        ];
    }
}
