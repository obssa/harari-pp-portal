<?php

namespace App\Filament\Pages;

use App\Domain\Analytics\Support\ReportPeriod;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Support\PeopleStats;
use App\Filament\Support\CurrentUser;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Arr;
use Illuminate\Support\Number;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * The membership report (PLAN.md §7, ADR-0050): members by woreda, sex, age
 * band, status, how they joined or the month they joined, optionally only
 * those who joined in a period; numbers only, for the woredas the viewer
 * looks after. Downloads show counts under five as "<5".
 */
class MembershipReports extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static ?string $slug = 'membership-report';

    protected static ?int $navigationSort = 50;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.people');
    }

    public static function getNavigationLabel(): string
    {
        return __('members.report.title');
    }

    public function getTitle(): string
    {
        return __('members.report.title');
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->can('members.view');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (array $filters): array => collect($this->rows($filters))->keyBy('key')->all())
            ->paginated(false)
            ->heading(fn (): string => __('members.report.heading', ['total' => Number::format(array_sum(array_column($this->rows($this->tableFilters ?? []), 'count')))]))
            ->description(__('members.report.help'))
            ->headerActions([
                Action::make('export')
                    ->label(__('membership.admin.export'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->visible(fn (): bool => CurrentUser::get()->can('members.export'))
                    ->action(function (): StreamedResponse {
                        abort_unless(CurrentUser::get()->can('members.export'), 403);
                        $filters = $this->tableFilters ?? [];
                        $dimension = $this->dimension($filters);
                        $rows = $this->rows($filters);
                        activity('membership')->causedBy(CurrentUser::get())->event('exported')
                            ->withProperties(['of' => 'membership_report', 'dimension' => $dimension])->log('Membership report exported');

                        return response()->streamDownload(function () use ($dimension, $rows): void {
                            $out = fopen('php://output', 'wb');
                            fwrite($out, "\u{FEFF}");
                            fputcsv($out, [PeopleStats::dimensionLabel($dimension), __('members.report.count'), __('analytics.column.share')], escape: '');
                            foreach ($rows as $row) {
                                fputcsv($out, [$row['label'], PeopleStats::shown($row['count']), $row['count'] < PeopleStats::SMALL ? '' : $row['share']], escape: '');
                            }
                            fclose($out);
                        }, "membership-{$dimension}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
                    }),
            ])
            ->columns([
                TextColumn::make('label')->label(fn (): string => PeopleStats::dimensionLabel($this->dimension($this->tableFilters ?? [])))->wrap(),
                TextColumn::make('count')->label(__('members.report.count'))->numeric()->alignEnd(),
                TextColumn::make('share')->label(__('analytics.column.share'))->formatStateUsing(fn (float $state): string => "{$state}%")->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('dimension')->label(__('analytics.column.dimension'))->default('woreda')->selectablePlaceholder(false)
                    ->options(collect(PeopleStats::DIMENSIONS)->mapWithKeys(fn (string $d): array => [$d => PeopleStats::dimensionLabel($d)])->all()),
                SelectFilter::make('status')->label(__('membership.admin.status'))->default(MemberStatus::Active->value)
                    ->placeholder(__('members.report.all_statuses'))
                    ->options(collect(MemberStatus::cases())->mapWithKeys(fn (MemberStatus $s): array => [$s->value => $s->label()])->all()),
                SelectFilter::make('joined')->label(__('members.report.joined'))->placeholder(__('members.report.any_time'))
                    ->options(ReportPeriod::options()),
            ])
            ->emptyStateHeading(__('members.report.empty'))
            ->emptyStateIcon(Heroicon::OutlinedUserGroup);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{key: string, label: string, count: int, share: float}>
     */
    private function rows(array $filters): array
    {
        $status = Arr::get($filters, 'status.value');
        $joined = (string) Arr::get($filters, 'joined.value', '');
        [$from, $to] = in_array($joined, ReportPeriod::KEYS, true) ? ReportPeriod::range($joined) : [null, null];

        return (new PeopleStats(CurrentUser::get()))->breakdown(
            $this->dimension($filters),
            MemberStatus::tryFrom((string) $status) !== null ? [(string) $status] : null,
            $from,
            $to,
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function dimension(array $filters): string
    {
        $value = Arr::get($filters, 'dimension.value');

        return in_array($value, PeopleStats::DIMENSIONS, true) ? (string) $value : 'woreda';
    }
}
