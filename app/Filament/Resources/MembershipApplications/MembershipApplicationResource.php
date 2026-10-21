<?php

namespace App\Filament\Resources\MembershipApplications;

use App\Domain\Access\Support\OfficeScope;
use App\Domain\Membership\Enums\ApplicationStatus;
use App\Domain\Membership\Models\MembershipApplication;
use App\Domain\Membership\Models\PersonNote;
use App\Domain\Membership\Support\ApplicationOptions;
use App\Domain\Membership\Support\DuplicateFinder;
use App\Domain\Membership\Support\Mask;
use App\Domain\Organisation\Enums\OfficeType;
use App\Domain\Organisation\Models\Office;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\MembershipApplications\Pages\ListMembershipApplications;
use App\Filament\Resources\MembershipApplications\Pages\ViewMembershipApplication;
use App\Filament\Support\CalendarDate;
use App\Filament\Support\CurrentUser;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
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
 * The review queue for membership applications (PLAN.md §5, ADR-0045): the
 * applications of the woredas one looks after, names and phones masked in
 * the list, the full profile with possible duplicates and the history on
 * the application's page.
 */
class MembershipApplicationResource extends Resource
{
    protected static ?string $model = MembershipApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'applications';

    public static function getModelLabel(): string
    {
        return __('membership.admin.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('membership.admin.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.people');
    }

    public static function getNavigationBadge(): ?string
    {
        $user = Filament::auth()->user();
        if (! $user instanceof User || ! $user->can('applications.view')) {
            return null;
        }
        $waiting = OfficeScope::apply(MembershipApplication::query(), $user, 'applications.view')
            ->where('status', ApplicationStatus::Submitted->value)->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    /**
     * @return Builder<MembershipApplication>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<MembershipApplication> $query */
        $query = parent::getEloquentQuery()->where('status', '!=', ApplicationStatus::Started->value)->whereNull('erased_at');

        return OfficeScope::apply($query, CurrentUser::get(), 'applications.view');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('submitted_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['woreda.translations']))
            ->columns([
                TextColumn::make('reference')->label(__('membership.reference'))->fontFamily('mono')->searchable()->copyable(),
                TextColumn::make('masked_name')->label(__('membership.field.name'))
                    ->state(fn (MembershipApplication $record): string => Mask::name((string) $record->name)),
                TextColumn::make('masked_phone')->label(__('membership.field.phone'))
                    ->state(fn (MembershipApplication $record): string => Mask::phone($record->phone))->fontFamily('mono'),
                TextColumn::make('status')->label(__('membership.admin.status'))->badge()
                    ->formatStateUsing(fn (ApplicationStatus $state): string => $state->label())
                    ->color(fn (ApplicationStatus $state): string => $state->color()),
                TextColumn::make('woreda')->label(__('membership.field.woreda_id'))
                    ->state(fn (MembershipApplication $record): ?string => $record->woreda?->name())->placeholder('—'),
                TextColumn::make('submitted_at')->label(__('membership.status.sent_on'))->formatStateUsing(CalendarDate::format(withTime: true))->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('membership.admin.status'))->default(ApplicationStatus::Submitted->value)
                    ->options(collect([ApplicationStatus::Submitted, ApplicationStatus::Approved, ApplicationStatus::Rejected, ApplicationStatus::Withdrawn])
                        ->mapWithKeys(fn (ApplicationStatus $s): array => [$s->value => $s->label()])->all()),
                SelectFilter::make('woreda_id')->label(__('membership.field.woreda_id'))
                    ->options(fn (): array => Office::query()->where('type', OfficeType::Woreda->value)->with('translations')->get()
                        ->mapWithKeys(fn (Office $office): array => [$office->id => $office->name()])->all()),
            ])
            ->recordActions([ViewAction::make()])
            ->recordUrl(fn (MembershipApplication $record): string => self::getUrl('view', ['record' => $record]))
            ->emptyStateHeading(__('membership.admin.empty'));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 2])->schema([
                Section::make(__('membership.admin.person'))->columns(2)->schema([
                    TextEntry::make('name')->label(__('membership.field.name')),
                    TextEntry::make('sex')->label(__('membership.field.sex'))->formatStateUsing(fn (?string $state): string => ApplicationOptions::sexes()[$state] ?? '—'),
                    TextEntry::make('birth_date')->label(__('membership.field.birth_date'))->formatStateUsing(CalendarDate::format())->placeholder('—'),
                    TextEntry::make('id_document')->label(__('membership.field.id_type'))
                        ->state(fn (MembershipApplication $record): string => (ApplicationOptions::idTypes()[$record->id_type] ?? '—').($record->id_number !== null ? ' · '.Mask::idNumber($record->id_number) : '')),
                    TextEntry::make('education')->label(__('membership.field.education'))->formatStateUsing(fn (?string $state): string => ApplicationOptions::education()[$state] ?? '—')->placeholder('—'),
                    TextEntry::make('occupation')->label(__('membership.field.occupation'))->placeholder('—'),
                    TextEntry::make('phone')->label(__('membership.field.phone'))->copyable()->placeholder('—'),
                    TextEntry::make('email')->label(__('membership.field.email'))->copyable()->placeholder('—'),
                ]),
                Section::make(__('membership.admin.place'))->columns(2)->schema([
                    TextEntry::make('woreda_name')->label(__('membership.field.woreda_id'))->state(fn (MembershipApplication $record): ?string => $record->woreda?->name())->placeholder('—'),
                    TextEntry::make('kebele_name')->label(__('membership.field.kebele_id'))->state(fn (MembershipApplication $record): ?string => $record->kebele?->name())->placeholder('—'),
                    TextEntry::make('basic_org_name')->label(__('membership.field.basic_org_id'))->state(fn (MembershipApplication $record): ?string => $record->basicOrganisation?->name())->placeholder('—'),
                    TextEntry::make('address_note')->label(__('membership.field.address_note'))->placeholder('—'),
                ]),
                Section::make(__('membership.admin.duplicates'))->schema(fn (?MembershipApplication $record): array => $record === null ? [] : self::duplicateEntries($record)),
                Section::make(__('membership.admin.history'))->collapsible()->schema(fn (?MembershipApplication $record): array => $record === null ? [] : self::historyEntries($record)),
            ]),
            Section::make(__('admin.posts.section.details'))->columnSpan(['default' => 3, 'lg' => 1])->schema([
                TextEntry::make('reference')->label(__('membership.reference'))->fontFamily('mono')->copyable(),
                TextEntry::make('status')->label(__('membership.admin.status'))->badge()
                    ->formatStateUsing(fn (ApplicationStatus $state): string => $state->label())->color(fn (ApplicationStatus $state): string => $state->color()),
                TextEntry::make('submitted_at')->label(__('membership.status.sent_on'))->formatStateUsing(CalendarDate::format(withTime: true)),
                TextEntry::make('phone_verified_at')->label(__('membership.admin.phone_verified'))->formatStateUsing(CalendarDate::format(withTime: true))->placeholder('—'),
                TextEntry::make('reviewer.name')->label(__('membership.admin.reviewer'))->placeholder('—'),
                TextEntry::make('decided_at')->label(__('membership.admin.decided_at'))->formatStateUsing(CalendarDate::format(withTime: true))->placeholder('—'),
                TextEntry::make('reason')->label(__('membership.admin.reason'))->placeholder('—'),
                TextEntry::make('member_no')->label(__('members.field.member_no'))->fontFamily('mono')
                    ->state(fn (MembershipApplication $record): ?string => $record->member?->member_no)
                    ->url(fn (MembershipApplication $record): ?string => $record->member !== null && CurrentUser::get()->can('view', $record->member)
                        ? MemberResource::getUrl('view', ['record' => $record->member]) : null)
                    ->visible(fn (MembershipApplication $record): bool => $record->member !== null),
            ]),
        ]);
    }

    /**
     * @return list<TextEntry>
     */
    private static function duplicateEntries(MembershipApplication $record): array
    {
        $matches = DuplicateFinder::for($record);
        if ($matches === []) {
            return [TextEntry::make('no_duplicates')->hiddenLabel()->state(__('membership.admin.no_duplicates'))];
        }

        return array_map(fn (array $match, int $i): TextEntry => TextEntry::make("duplicate_{$i}")
            ->label($match['type'] === 'member' ? __('membership.admin.duplicate_member') : __('membership.admin.duplicate_application'))
            ->state($match['label'].' — '.implode(', ', $match['reasons']))
            ->color('warning')
            ->url($match['type'] === 'application' ? self::getUrl('view', ['record' => $match['id']]) : MemberResource::getUrl('view', ['record' => $match['id']])), $matches, array_keys($matches));
    }

    /**
     * @return list<TextEntry>
     */
    private static function historyEntries(MembershipApplication $record): array
    {
        $notes = $record->notes()->with('user')->get();
        if ($notes->isEmpty()) {
            return [TextEntry::make('no_history')->hiddenLabel()->state(__('membership.admin.no_history'))];
        }

        return $notes->values()->map(fn (PersonNote $note, int $i): TextEntry => TextEntry::make("note_{$i}")
            ->label((string) CalendarDate::format(withTime: true)($note->created_at).' · '.($note->user->name ?? '—').' · '.__("membership.note.kind.{$note->kind}"))
            ->state($note->body)
            ->extraAttributes(['class' => 'whitespace-pre-line']))->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMembershipApplications::route('/'),
            'view' => ViewMembershipApplication::route('/{record}'),
        ];
    }
}
