<?php

namespace App\Filament\Resources\Members;

use App\Domain\Access\Support\OfficeScope;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipApplication;
use App\Domain\Membership\Models\PersonNote;
use App\Domain\Membership\Support\ApplicationOptions;
use App\Domain\Membership\Support\Mask;
use App\Domain\Organisation\Enums\OfficeType;
use App\Domain\Organisation\Models\Office;
use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Filament\Resources\MembershipApplications\MembershipApplicationResource;
use App\Filament\Support\CalendarDate;
use App\Filament\Support\CurrentUser;
use App\Support\PhoneNumber;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The members directory (PLAN.md §5, ADR-0046): members of the woredas one
 * looks after, names and phones masked in the list, searched by member
 * number or phone; the full record, renewal and history on the member's
 * page.
 */
class MemberResource extends Resource
{
    protected static ?string $model = Member::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        return __('members.admin.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('members.admin.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.people');
    }

    /**
     * @return Builder<Member>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Member> $query */
        $query = parent::getEloquentQuery()->whereNull('erased_at');

        return OfficeScope::apply($query, CurrentUser::get(), 'members.view');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('membership.admin.person'))->columns(2)->schema([
                TextInput::make('name')->label(__('membership.field.name'))->required()->maxLength(120),
                Select::make('sex')->label(__('membership.field.sex'))->options(ApplicationOptions::sexes())->required(),
                DatePicker::make('birth_date')->label(__('membership.field.birth_date'))->maxDate(now()->subYears(ApplicationOptions::MINIMUM_AGE)),
                Select::make('education')->label(__('membership.field.education'))->options(ApplicationOptions::education()),
                Select::make('id_type')->label(__('membership.field.id_type'))->options(ApplicationOptions::idTypes())->default('none')->required()->live(),
                TextInput::make('id_number')->label(__('membership.field.id_number'))->maxLength(40)
                    ->visible(fn (Get $get): bool => $get('id_type') !== 'none')
                    ->helperText(fn (?Member $record): ?string => $record?->id_number !== null ? __('members.admin.id_keep', ['ending' => Mask::idNumber($record->id_number)]) : null),
                TextInput::make('occupation')->label(__('membership.field.occupation'))->maxLength(120),
            ]),
            Section::make(__('membership.admin.place'))->columns(2)->schema([
                Select::make('woreda_id')->label(__('membership.field.woreda_id'))->searchable()->options(fn (): array => self::offices(OfficeType::Woreda, true)),
                Select::make('kebele_id')->label(__('membership.field.kebele_id'))->searchable()->options(fn (): array => self::offices(OfficeType::Kebele)),
                Select::make('basic_org_id')->label(__('membership.field.basic_org_id'))->searchable()->options(fn (): array => self::offices(OfficeType::BasicOrganisation)),
                Textarea::make('address_note')->label(__('membership.field.address_note'))->rows(2)->maxLength(200),
                TextInput::make('phone')->label(__('membership.field.phone'))->tel()->maxLength(20),
                TextInput::make('email')->label(__('membership.field.email'))->email()->maxLength(190),
            ]),
            Section::make(__('members.admin.membership'))->columns(2)->schema([
                DatePicker::make('joined_on')->label(__('members.field.joined_on'))->required()->default(now())->maxDate(now()),
                DatePicker::make('renewal_due_on')->label(__('members.field.renewal_due_on'))->helperText(__('members.admin.due_help')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('member_no', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['woreda.translations']))
            ->searchPlaceholder(__('members.admin.search'))
            // Phone numbers are typed with spaces; search the whole text.
            ->splitSearchTerms(false)
            ->columns([
                TextColumn::make('member_no')->label(__('members.field.member_no'))->fontFamily('mono')->sortable()->copyable()
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::search($query, $search)),
                TextColumn::make('masked_name')->label(__('membership.field.name'))
                    ->state(fn (Member $record): string => Mask::name($record->name)),
                TextColumn::make('masked_phone')->label(__('membership.field.phone'))
                    ->state(fn (Member $record): string => Mask::phone($record->phone))->fontFamily('mono'),
                TextColumn::make('status')->label(__('membership.admin.status'))->badge()
                    ->formatStateUsing(fn (MemberStatus $state): string => $state->label())
                    ->color(fn (MemberStatus $state): string => $state->color()),
                TextColumn::make('woreda')->label(__('membership.field.woreda_id'))
                    ->state(fn (Member $record): ?string => $record->woreda?->name())->placeholder('—'),
                TextColumn::make('joined_on')->label(__('members.field.joined_on'))->formatStateUsing(CalendarDate::format())->sortable(),
                TextColumn::make('renewal_due_on')->label(__('members.field.renewal_due_on'))->formatStateUsing(CalendarDate::format())->sortable()
                    ->color(fn (Member $record): ?string => $record->renewal_due_on !== null && $record->renewal_due_on->isPast() ? 'danger' : null)
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('membership.admin.status'))
                    ->options(collect(MemberStatus::cases())->mapWithKeys(fn (MemberStatus $s): array => [$s->value => $s->label()])->all()),
                SelectFilter::make('woreda_id')->label(__('membership.field.woreda_id'))->options(fn (): array => self::offices(OfficeType::Woreda)),
                SelectFilter::make('renewal')->label(__('members.admin.renewal'))
                    ->options(['soon' => __('members.admin.renewal_soon', ['days' => (int) config('portal.membership_reminder_days')]), 'overdue' => __('members.admin.renewal_overdue')])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'soon' => $query->whereBetween('renewal_due_on', [CarbonImmutable::today()->toDateString(), CarbonImmutable::today()->addDays((int) config('portal.membership_reminder_days'))->toDateString()]),
                        'overdue' => $query->where('renewal_due_on', '<', CarbonImmutable::today()->toDateString()),
                        default => $query,
                    }),
            ])
            ->recordActions([ViewAction::make()])
            ->recordUrl(fn (Member $record): string => self::getUrl('view', ['record' => $record]))
            ->emptyStateHeading(__('members.admin.empty'));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 2])->schema([
                Section::make(__('membership.admin.person'))->columns(2)->schema([
                    TextEntry::make('name')->label(__('membership.field.name')),
                    TextEntry::make('sex')->label(__('membership.field.sex'))->formatStateUsing(fn (?string $state): string => ApplicationOptions::sexes()[$state] ?? '—')->placeholder('—'),
                    TextEntry::make('birth_date')->label(__('membership.field.birth_date'))->formatStateUsing(CalendarDate::format())->placeholder('—'),
                    TextEntry::make('id_document')->label(__('membership.field.id_type'))
                        ->state(fn (Member $record): string => (ApplicationOptions::idTypes()[$record->id_type] ?? '—').($record->id_number !== null ? ' · '.Mask::idNumber($record->id_number) : '')),
                    TextEntry::make('education')->label(__('membership.field.education'))->formatStateUsing(fn (?string $state): string => ApplicationOptions::education()[$state] ?? '—')->placeholder('—'),
                    TextEntry::make('occupation')->label(__('membership.field.occupation'))->placeholder('—'),
                    TextEntry::make('phone')->label(__('membership.field.phone'))->copyable()->placeholder('—'),
                    TextEntry::make('email')->label(__('membership.field.email'))->copyable()->placeholder('—'),
                ]),
                Section::make(__('membership.admin.place'))->columns(2)->schema([
                    TextEntry::make('woreda_name')->label(__('membership.field.woreda_id'))->state(fn (Member $record): ?string => $record->woreda?->name())->placeholder('—'),
                    TextEntry::make('kebele_name')->label(__('membership.field.kebele_id'))->state(fn (Member $record): ?string => $record->kebele?->name())->placeholder('—'),
                    TextEntry::make('basic_org_name')->label(__('membership.field.basic_org_id'))->state(fn (Member $record): ?string => $record->basicOrganisation?->name())->placeholder('—'),
                    TextEntry::make('address_note')->label(__('membership.field.address_note'))->placeholder('—'),
                ]),
                Section::make(__('membership.admin.history'))->collapsible()->schema(fn (?Member $record): array => $record === null ? [] : self::historyEntries($record)),
            ]),
            Section::make(__('members.admin.membership'))->columnSpan(['default' => 3, 'lg' => 1])->schema([
                TextEntry::make('member_no')->label(__('members.field.member_no'))->fontFamily('mono')->copyable(),
                TextEntry::make('status')->label(__('membership.admin.status'))->badge()
                    ->formatStateUsing(fn (MemberStatus $state): string => $state->label())->color(fn (MemberStatus $state): string => $state->color()),
                TextEntry::make('joined_on')->label(__('members.field.joined_on'))->formatStateUsing(CalendarDate::format()),
                TextEntry::make('renewed_on')->label(__('members.field.renewed_on'))->formatStateUsing(CalendarDate::format())->placeholder('—'),
                TextEntry::make('renewal_due_on')->label(__('members.field.renewal_due_on'))->formatStateUsing(CalendarDate::format())->placeholder('—')
                    ->color(fn (Member $record): ?string => $record->renewal_due_on !== null && $record->renewal_due_on->isPast() ? 'danger' : null),
                TextEntry::make('source')->label(__('members.field.source'))->formatStateUsing(fn (string $state): string => __("members.source.{$state}")),
                TextEntry::make('application_reference')->label(__('members.field.application'))
                    ->state(fn (Member $record): ?string => $record->application?->reference)->placeholder('—')
                    ->url(fn (Member $record): ?string => $record->application !== null && CurrentUser::get()->can('view', $record->application)
                        ? MembershipApplicationResource::getUrl('view', ['record' => $record->application]) : null),
            ]),
        ]);
    }

    /**
     * Offices of a type by name; for woredas in the form, only those the person may place members in.
     *
     * @return array<string, string>
     */
    public static function offices(OfficeType $type, bool $inScope = false): array
    {
        $query = Office::query()->where('type', $type->value)->with('translations');
        $user = CurrentUser::get();
        if ($inScope && ($ids = OfficeScope::officeIds($user, $user->can('members.update') ? 'members.update' : 'members.create')) !== null) {
            $query->whereKey($ids);
        }

        return $query->get()->mapWithKeys(fn (Office $office): array => [$office->id => $office->name()])->sortBy(fn (string $name): string => $name)->all();
    }

    /**
     * Encrypted names cannot be searched; member numbers and phone numbers (through their hash) can.
     *
     * @param  Builder<Member>  $query
     * @return Builder<Member>
     */
    private static function search(Builder $query, string $search): Builder
    {
        $search = trim($search);
        $phone = PhoneNumber::normalise($search);

        return $query->where(fn (Builder $q) => $q->where('member_no', 'like', '%'.strtoupper($search).'%')
            ->when($phone !== null, fn (Builder $q) => $q->orWhere('phone_hash', MembershipApplication::hash((string) $phone))));
    }

    /**
     * @return list<TextEntry>
     */
    private static function historyEntries(Member $record): array
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

    public static function getPages(): array
    {
        return [
            'index' => ListMembers::route('/'),
            'create' => CreateMember::route('/create'),
            'view' => ViewMember::route('/{record}'),
            'edit' => EditMember::route('/{record}/edit'),
        ];
    }
}
