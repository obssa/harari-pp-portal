<?php

namespace App\Filament\Resources\Volunteers;

use App\Domain\Access\Support\OfficeScope;
use App\Domain\Membership\Models\PersonNote;
use App\Domain\Membership\Support\Mask;
use App\Domain\Organisation\Enums\OfficeType;
use App\Domain\Volunteers\Enums\AssignmentStatus;
use App\Domain\Volunteers\Enums\VolunteerStatus;
use App\Domain\Volunteers\Models\Volunteer;
use App\Domain\Volunteers\Support\VolunteerOptions;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\Volunteers\Pages\CreateVolunteer;
use App\Filament\Resources\Volunteers\Pages\EditVolunteer;
use App\Filament\Resources\Volunteers\Pages\ListVolunteers;
use App\Filament\Resources\Volunteers\Pages\ViewVolunteer;
use App\Filament\Resources\Volunteers\RelationManagers\AssignmentsRelationManager;
use App\Filament\Support\CalendarDate;
use App\Filament\Support\CurrentUser;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Volunteers (PLAN.md §5, ADR-0048): those of the woredas one looks after,
 * names and phones masked in the list, filtered by what they offer; their
 * tasks and hours on their page.
 */
class VolunteerResource extends Resource
{
    protected static ?string $model = Volunteer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHandRaised;

    protected static ?int $navigationSort = 40;

    public static function getModelLabel(): string
    {
        return __('volunteers.admin.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('volunteers.admin.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.people');
    }

    public static function getNavigationBadge(): ?string
    {
        $user = CurrentUser::get();
        if (! $user->can('volunteers.view')) {
            return null;
        }
        $new = OfficeScope::apply(Volunteer::query()->whereNull('erased_at'), $user, 'volunteers.view')->where('status', VolunteerStatus::New->value)->count();

        return $new > 0 ? (string) $new : null;
    }

    /**
     * @return Builder<Volunteer>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Volunteer> $query */
        $query = parent::getEloquentQuery()->whereNull('erased_at');

        return OfficeScope::apply($query, CurrentUser::get(), 'volunteers.view');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('volunteers.admin.contact'))->columns(2)->schema([
                TextInput::make('name')->label(__('membership.field.name'))->required()->maxLength(120)->columnSpanFull(),
                TextInput::make('phone')->label(__('membership.field.phone'))->tel()->maxLength(20),
                TextInput::make('email')->label(__('membership.field.email'))->email()->maxLength(190),
                Select::make('woreda_id')->label(__('membership.field.woreda_id'))->searchable()
                    ->options(fn (): array => MemberResource::offices(OfficeType::Woreda)),
            ]),
            Section::make(__('volunteers.admin.offers'))->schema([
                CheckboxList::make('skills')->label(__('volunteers.form.skills'))->options(VolunteerOptions::skills())->columns(3),
                CheckboxList::make('availability')->label(__('volunteers.form.availability'))->options(VolunteerOptions::availability())->columns(4),
                CheckboxList::make('languages')->label(__('volunteers.form.languages'))->options(VolunteerOptions::languages())->columns(6),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $hasChoice = fn (string $column): \Closure => fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
            ? $query->whereJsonContains($column, (string) $data['value']) : $query;

        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['woreda.translations'])
                ->withSum(['assignments as hours_done' => fn (Builder $q) => $q->where('status', AssignmentStatus::Done->value)], 'hours'))
            ->columns([
                TextColumn::make('masked_name')->label(__('membership.field.name'))
                    ->state(fn (Volunteer $record): string => Mask::name($record->name)),
                TextColumn::make('masked_phone')->label(__('membership.field.phone'))
                    ->state(fn (Volunteer $record): string => Mask::phone($record->phone))->fontFamily('mono'),
                TextColumn::make('status')->label(__('membership.admin.status'))->badge()
                    ->formatStateUsing(fn (VolunteerStatus $state): string => $state->label())->color(fn (VolunteerStatus $state): string => $state->color()),
                TextColumn::make('woreda')->label(__('membership.field.woreda_id'))
                    ->state(fn (Volunteer $record): ?string => $record->woreda?->name())->placeholder('—'),
                TextColumn::make('skills')->label(__('volunteers.form.skills'))->badge()->limitList(3)
                    ->formatStateUsing(fn (string $state): string => VolunteerOptions::skills()[$state] ?? $state),
                TextColumn::make('hours_done')->label(__('volunteers.assignment.hours'))->numeric(decimalPlaces: 1)->placeholder('—')->sortable(),
                TextColumn::make('created_at')->label(__('volunteers.admin.signed_up'))->formatStateUsing(CalendarDate::format())->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('membership.admin.status'))->options(VolunteerStatus::options()),
                SelectFilter::make('skill')->label(__('volunteers.form.skills'))->options(VolunteerOptions::skills())->query($hasChoice('skills')),
                SelectFilter::make('availability')->label(__('volunteers.form.availability'))->options(VolunteerOptions::availability())->query($hasChoice('availability')),
                SelectFilter::make('language')->label(__('volunteers.form.languages'))->options(VolunteerOptions::languages())->query($hasChoice('languages')),
                SelectFilter::make('woreda_id')->label(__('membership.field.woreda_id'))->options(fn (): array => MemberResource::offices(OfficeType::Woreda)),
            ])
            ->recordActions([ViewAction::make()])
            ->recordUrl(fn (Volunteer $record): string => self::getUrl('view', ['record' => $record]))
            ->emptyStateHeading(__('volunteers.admin.empty'));
    }

    public static function infolist(Schema $schema): Schema
    {
        $list = fn (array $labels): \Closure => fn (?array $state): string => $state === null || $state === []
            ? '—' : implode(', ', array_map(fn (string $key): string => $labels[$key] ?? $key, $state));

        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 2])->schema([
                Section::make(__('volunteers.admin.contact'))->columns(2)->schema([
                    TextEntry::make('name')->label(__('membership.field.name')),
                    TextEntry::make('woreda_name')->label(__('membership.field.woreda_id'))->state(fn (Volunteer $record): ?string => $record->woreda?->name())->placeholder('—'),
                    TextEntry::make('phone')->label(__('membership.field.phone'))->copyable()->placeholder('—'),
                    TextEntry::make('email')->label(__('membership.field.email'))->copyable()->placeholder('—'),
                ]),
                Section::make(__('volunteers.admin.offers'))->schema([
                    TextEntry::make('skills')->label(__('volunteers.form.skills'))->state(fn (Volunteer $record): string => $list(VolunteerOptions::skills())($record->skills)),
                    TextEntry::make('availability')->label(__('volunteers.form.availability'))->state(fn (Volunteer $record): string => $list(VolunteerOptions::availability())($record->availability)),
                    TextEntry::make('languages')->label(__('volunteers.form.languages'))->state(fn (Volunteer $record): string => $list(VolunteerOptions::languages())($record->languages)),
                    TextEntry::make('message')->label(__('volunteers.form.message'))->placeholder('—')->extraAttributes(['class' => 'whitespace-pre-line']),
                ]),
                Section::make(__('membership.admin.history'))->collapsible()->schema(fn (?Volunteer $record): array => $record === null ? [] : self::historyEntries($record)),
            ]),
            Section::make(__('volunteers.admin.standing'))->columnSpan(['default' => 3, 'lg' => 1])->schema([
                TextEntry::make('status')->label(__('membership.admin.status'))->badge()
                    ->formatStateUsing(fn (VolunteerStatus $state): string => $state->label())->color(fn (VolunteerStatus $state): string => $state->color()),
                TextEntry::make('created_at')->label(__('volunteers.admin.signed_up'))->formatStateUsing(CalendarDate::format(withTime: true)),
                TextEntry::make('source')->label(__('members.field.source'))->formatStateUsing(fn (string $state): string => __("volunteers.source.{$state}")),
                TextEntry::make('member_no')->label(__('volunteers.admin.member'))->fontFamily('mono')
                    ->state(fn (Volunteer $record): ?string => $record->member?->member_no)->placeholder(__('volunteers.admin.not_member'))
                    ->url(fn (Volunteer $record): ?string => $record->member !== null && CurrentUser::get()->can('view', $record->member)
                        ? MemberResource::getUrl('view', ['record' => $record->member]) : null),
            ]),
        ]);
    }

    /**
     * @return list<TextEntry>
     */
    private static function historyEntries(Volunteer $record): array
    {
        $notes = $record->notes()->with('user')->get();
        if ($notes->isEmpty()) {
            return [TextEntry::make('no_history')->hiddenLabel()->state(__('membership.admin.no_history'))];
        }

        return $notes->values()->map(fn (PersonNote $note, int $i): TextEntry => TextEntry::make("note_{$i}")
            ->label((string) CalendarDate::format(withTime: true)($note->created_at).' · '.($note->user->name ?? __('members.admin.system')).' · '.__("membership.note.kind.{$note->kind}"))
            ->state($note->body ?? '—')
            ->extraAttributes(['class' => 'whitespace-pre-line']))->all();
    }

    public static function getRelations(): array
    {
        return [AssignmentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVolunteers::route('/'),
            'create' => CreateVolunteer::route('/create'),
            'view' => ViewVolunteer::route('/{record}'),
            'edit' => EditVolunteer::route('/{record}/edit'),
        ];
    }
}
