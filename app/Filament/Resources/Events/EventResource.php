<?php

namespace App\Filament\Resources\Events;

use App\Domain\Events\Enums\RegistrationStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Media\Enums\MediaKind;
use App\Domain\Organisation\Models\League;
use App\Domain\Organisation\Models\Office;
use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\Pages\ListEvents;
use App\Filament\Resources\Events\Pages\ViewEvent;
use App\Filament\Resources\Events\RelationManagers\RegistrationsRelationManager;
use App\Filament\Resources\Offices\OfficeResource;
use App\Filament\Support\CalendarDate;
use App\Filament\Support\CurrentUser;
use App\Filament\Support\DirectoryFields;
use App\Filament\Support\MediaPicker;
use App\Filament\Support\Translatable;
use App\Filament\Support\ViewOnSite;
use App\Services\Calendar\EthiopianCalendar;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        return __('events.admin.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('events.admin.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.content');
    }

    public static function form(Schema $schema): Schema
    {
        $timezone = EthiopianCalendar::timezone();

        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 2])->schema([
                Translatable::tabs(fn (string $locale): array => [
                    Translatable::mainField($locale, 'title', 200)->label(__('admin.posts.field.title')),
                    Translatable::slugField($locale, 'event_translations'),
                    Textarea::make("translations.{$locale}.summary")->label(__('events.admin.field.summary'))->rows(2)->maxLength(400),
                    DirectoryFields::body($locale, __('events.admin.field.body')),
                    TextInput::make("translations.{$locale}.venue")->label(__('events.admin.field.venue'))->maxLength(200),
                    Textarea::make("translations.{$locale}.address")->label(__('events.admin.field.address'))->rows(2)->maxLength(400),
                    Repeater::make("translations.{$locale}.agenda")
                        ->label(__('events.admin.field.agenda'))
                        ->reorderableWithButtons()
                        ->defaultItems(0)
                        ->addActionLabel(__('events.admin.add_agenda'))
                        ->columns(4)
                        ->schema([
                            TextInput::make('time')->label(__('events.admin.field.agenda_time'))->placeholder('09:00')->maxLength(20),
                            TextInput::make('item')->label(__('events.admin.field.agenda_item'))->maxLength(300)->columnSpan(3),
                        ]),
                    DirectoryFields::published($locale, 'events.manage'),
                ]),
            ]),
            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 1])->schema([
                Section::make(__('events.admin.section.when'))->schema([
                    DateTimePicker::make('starts_at')->label(__('events.admin.field.starts_at'))->seconds(false)->timezone($timezone)->required(),
                    DateTimePicker::make('ends_at')->label(__('events.admin.field.ends_at'))->seconds(false)->timezone($timezone)->after('starts_at'),
                    Toggle::make('all_day')->label(__('events.admin.field.all_day')),
                ]),
                Section::make(__('events.admin.section.where'))->schema([
                    TextInput::make('online_url')->label(__('events.admin.field.online_url'))->url()->startsWith(['https://'])->maxLength(500),
                    Grid::make(2)->schema([
                        TextInput::make('latitude')->label(__('admin.offices.field.latitude'))->numeric()->minValue(-90)->maxValue(90),
                        TextInput::make('longitude')->label(__('admin.offices.field.longitude'))->numeric()->minValue(-180)->maxValue(180),
                    ]),
                ]),
                Section::make(__('admin.posts.section.details'))->schema([
                    MediaPicker::make('cover_id', [MediaKind::Image])->label(__('admin.posts.field.featured_image')),
                    Select::make('speakers')->label(__('events.admin.field.speakers'))->multiple()->searchable()
                        ->options(fn (): array => OfficeResource::leaderOptions()),
                    Select::make('office_id')->label(__('admin.leaders.field.office'))->searchable()
                        ->options(fn (): array => Office::query()->with('translations')->orderBy('sort')->get()
                            ->mapWithKeys(fn (Office $o): array => [$o->id => $o->name()])->all()),
                    Select::make('league_id')->label(__('admin.leaders.field.league'))
                        ->options(fn (): array => League::query()->with('translations')->orderBy('sort')->get()
                            ->mapWithKeys(fn (League $l): array => [$l->id => $l->name()])->all()),
                ]),
                Section::make(__('events.admin.section.registration'))->collapsible()->schema([
                    Toggle::make('registration_enabled')->label(__('events.admin.field.registration_enabled'))->live(),
                    TextInput::make('capacity')->label(__('events.admin.field.capacity'))->helperText(__('events.admin.help.capacity'))
                        ->numeric()->integer()->minValue(1)
                        ->visible(fn (Get $get): bool => (bool) $get('registration_enabled')),
                    Toggle::make('waitlist')->label(__('events.admin.field.waitlist'))->default(true)
                        ->visible(fn (Get $get): bool => (bool) $get('registration_enabled')),
                    DateTimePicker::make('registration_closes_at')->label(__('events.admin.field.registration_closes_at'))->seconds(false)->timezone($timezone)
                        ->visible(fn (Get $get): bool => (bool) $get('registration_enabled')),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('translations')
                ->withCount(['registrations as registered_count' => fn (Builder $q) => $q->where('status', RegistrationStatus::Registered->value)]))
            ->columns([
                TextColumn::make('title')->label(__('admin.posts.field.title'))
                    ->state(fn (Event $record): string => $record->title()),
                TextColumn::make('starts_at')->label(__('events.admin.field.starts_at'))
                    ->formatStateUsing(CalendarDate::format(withTime: true))->sortable(),
                TextColumn::make('registered_count')->label(__('events.registration.admin.registered'))
                    ->state(fn (Event $record): string => $record->registration_enabled
                        ? $record->getAttribute('registered_count').($record->capacity !== null ? ' / '.$record->capacity : '')
                        : '—'),
                DirectoryFields::languagesColumn(),
            ])
            ->filters([
                TernaryFilter::make('upcoming')->label(__('events.admin.filter.upcoming'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->scopes(['upcoming']),
                        false: fn (Builder $query): Builder => $query->scopes(['past']),
                    ),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewOnSite::row(),
                ViewAction::make()->hidden(fn (Event $record): bool => CurrentUser::get()->can('update', $record)),
                EditAction::make(), DeleteAction::make(), RestoreAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [RegistrationsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvents::route('/'),
            'create' => CreateEvent::route('/create'),
            'view' => ViewEvent::route('/{record}'),
            'edit' => EditEvent::route('/{record}/edit'),
        ];
    }

    /**
     * @return Builder<Event>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Event> */
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
