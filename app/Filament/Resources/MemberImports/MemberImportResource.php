<?php

namespace App\Filament\Resources\MemberImports;

use App\Domain\Membership\Actions\ImportMembers;
use App\Domain\Membership\Models\MemberImport;
use App\Filament\Resources\MemberImports\Pages\ListMemberImports;
use App\Filament\Resources\MemberImports\Pages\ViewMemberImport;
use App\Filament\Support\CalendarDate;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Imports of existing membership rolls (PLAN.md §5, ADR-0047): each import
 * with what the check found, then applied or discarded, and rolled back
 * within 30 days if it was a mistake.
 */
class MemberImportResource extends Resource
{
    protected static ?string $model = MemberImport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'member-imports';

    public static function getModelLabel(): string
    {
        return __('members.import.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('members.import.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.people');
    }

    public static function statusLabel(string $status): string
    {
        return __("members.import.status.{$status}");
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            MemberImport::CHECKED => 'warning',
            MemberImport::APPLYING => 'info',
            MemberImport::APPLIED => 'success',
            MemberImport::FAILED => 'danger',
            default => 'gray',
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('user'))
            ->columns([
                TextColumn::make('created_at')->label(__('members.import.when'))->formatStateUsing(CalendarDate::format(withTime: true))->sortable(),
                TextColumn::make('file_name')->label(__('members.import.file'))->limit(40),
                TextColumn::make('status')->label(__('membership.admin.status'))->badge()
                    ->formatStateUsing(fn (string $state): string => self::statusLabel($state))->color(fn (string $state): string => self::statusColor($state)),
                TextColumn::make('total')->label(__('members.import.total'))->numeric(),
                TextColumn::make('ready')->label(__('members.import.ready'))->numeric(),
                TextColumn::make('with_problems')->label(__('members.import.with_problems'))->numeric(),
                TextColumn::make('duplicates')->label(__('members.import.duplicates'))->numeric(),
                TextColumn::make('created_count')->label(__('members.import.created'))->numeric(),
                TextColumn::make('user.name')->label(__('members.import.by'))->placeholder('—'),
            ])
            ->recordActions([ViewAction::make()])
            ->recordUrl(fn (MemberImport $record): string => self::getUrl('view', ['record' => $record]))
            ->emptyStateHeading(__('members.import.empty'))
            ->emptyStateDescription(__('members.import.empty_help'));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('members.import.summary'))->schema([
                Grid::make(['default' => 2, 'md' => 4])->schema([
                    TextEntry::make('status')->label(__('membership.admin.status'))->badge()
                        ->formatStateUsing(fn (string $state): string => self::statusLabel($state))->color(fn (string $state): string => self::statusColor($state)),
                    TextEntry::make('file_name')->label(__('members.import.file')),
                    TextEntry::make('created_at')->label(__('members.import.when'))->formatStateUsing(CalendarDate::format(withTime: true)),
                    TextEntry::make('user.name')->label(__('members.import.by'))->placeholder('—'),
                    TextEntry::make('total')->label(__('members.import.total')),
                    TextEntry::make('ready')->label(__('members.import.ready'))->color('success'),
                    TextEntry::make('with_problems')->label(__('members.import.with_problems'))->color(fn (int $state): ?string => $state > 0 ? 'danger' : null),
                    TextEntry::make('duplicates')->label(__('members.import.duplicates'))->color(fn (int $state): ?string => $state > 0 ? 'warning' : null),
                    TextEntry::make('created_count')->label(__('members.import.created'))
                        ->visible(fn (MemberImport $record): bool => in_array($record->status, [MemberImport::APPLIED, MemberImport::FAILED, MemberImport::ROLLED_BACK], true)),
                    TextEntry::make('applied_at')->label(__('members.import.applied_at'))->formatStateUsing(CalendarDate::format(withTime: true))->placeholder('—'),
                    TextEntry::make('rolled_back_at')->label(__('members.import.rolled_back_at'))->formatStateUsing(CalendarDate::format(withTime: true))
                        ->visible(fn (MemberImport $record): bool => $record->rolled_back_at !== null),
                ]),
                TextEntry::make('next')->hiddenLabel()->state(fn (MemberImport $record): string => __("members.import.next.{$record->status}", ['days' => MemberImport::ROLLBACK_DAYS])),
            ]),
            Section::make(__('members.import.problems'))
                ->description(__('members.import.problems_help', ['max' => ImportMembers::MAX_PROBLEMS]))
                ->schema([
                    TextEntry::make('problem_lines')->hiddenLabel()->listWithLineBreaks()->limitList(50)->expandableLimitedList()->placeholder(__('members.import.no_problems'))
                        ->state(fn (MemberImport $record): array => self::problemLines($record)),
                ]),
        ]);
    }

    /**
     * "Row 12 · Phone: not a mobile number", for the screen and the download.
     *
     * @return list<string>
     */
    public static function problemLines(MemberImport $import): array
    {
        return array_map(fn (array $problem): string => __('members.import.problem_line', [
            'row' => $problem['row'],
            'field' => __("members.import.field.{$problem['field']}"),
            'problem' => __("members.import.problem.{$problem['problem']}"),
        ]).($problem['level'] === ImportMembers::WARNING ? ' ('.__('members.import.imported_anyway').')' : ''), $import->problems);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMemberImports::route('/'),
            'view' => ViewMemberImport::route('/{record}'),
        ];
    }
}
