<?php

namespace App\Filament\Pages;

use App\Domain\Analytics\Models\AnalyticsReport;
use App\Domain\Analytics\Support\ReportCsv;
use App\Domain\Analytics\Support\ReportPeriod;
use App\Domain\Analytics\Support\TrafficReport;
use App\Domain\Translation\Support\Locales;
use App\Filament\Resources\AnalyticsReports\AnalyticsReportResource;
use App\Filament\Support\CurrentUser;
use App\Models\User;
use App\Services\Calendar\EthiopianCalendar;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
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
 * The report builder (PLAN.md §7, ADR-0040): page views of the public site
 * by page, section, language, referring site, device or country over a
 * period, for one language or all; exported as CSV or emailed on a schedule.
 */
class TrafficReports extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $slug = 'analytics';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.insights');
    }

    public static function getNavigationLabel(): string
    {
        return __('analytics.title');
    }

    public function getTitle(): string
    {
        return __('analytics.title');
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->can('analytics.view');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (array $filters): array => collect($this->report($filters)->rows($this->dimension($filters), 200))->keyBy('key')->all())
            ->paginated(false)
            ->heading(fn (): string => __('analytics.heading', [
                'total' => Number::format($this->report($this->tableFilters ?? [])->total()),
                'period' => $this->periodText($this->tableFilters ?? []),
            ]))
            ->description(__('analytics.help'))
            ->headerActions([
                Action::make('export')
                    ->label(__('analytics.export'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->visible(fn (): bool => CurrentUser::get()->can('analytics.export'))
                    ->action(function (): StreamedResponse {
                        abort_unless(CurrentUser::get()->can('analytics.export'), 403);
                        $filters = $this->tableFilters ?? [];
                        $dimension = $this->dimension($filters);
                        $csv = ReportCsv::make($dimension, $this->report($filters)->rows($dimension, 5000));

                        return response()->streamDownload(function () use ($csv): void {
                            echo $csv;
                        }, "traffic-{$dimension}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
                    }),
                Action::make('schedule')
                    ->label(__('analytics.schedule.action'))
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->color('gray')
                    ->visible(fn (): bool => CurrentUser::get()->can('analytics.export'))
                    ->fillForm(fn (): array => [
                        'dimension' => $this->dimension($this->tableFilters ?? []),
                        'locale_filter' => $this->language($this->tableFilters ?? []),
                        'frequency' => AnalyticsReport::WEEKLY,
                        'mail_locale' => in_array(app()->getLocale(), ['en', 'am', 'om'], true) ? app()->getLocale() : 'en',
                    ])
                    ->schema(AnalyticsReportResource::fields())
                    ->action(function (array $data): void {
                        abort_unless(CurrentUser::get()->can('analytics.export'), 403);
                        $report = new AnalyticsReport($data);
                        $report->created_by = CurrentUser::get()->id;
                        $report->save();
                        Notification::make()->success()->title(__('analytics.schedule.saved'))->send();
                    }),
            ])
            ->columns([
                TextColumn::make('label')->label(fn (): string => TrafficReport::dimensionLabel($this->dimension($this->tableFilters ?? [])))->wrap()
                    ->extraAttributes(fn (array $record): array => $record['locale'] !== null ? ['lang' => $record['locale']] : []),
                TextColumn::make('views')->label(__('analytics.column.views'))->numeric()->alignEnd(),
                TextColumn::make('share')->label(__('analytics.column.share'))->formatStateUsing(fn (float $state): string => "{$state}%")->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('dimension')->label(__('analytics.column.dimension'))->default('pages')->selectablePlaceholder(false)
                    ->options(collect(TrafficReport::DIMENSIONS)->mapWithKeys(fn (string $d): array => [$d => TrafficReport::dimensionLabel($d)])->all()),
                SelectFilter::make('period')->label(__('analytics.column.period'))->default('30')->selectablePlaceholder(false)
                    ->options(ReportPeriod::options()),
                SelectFilter::make('language')->label(__('analytics.column.language'))->placeholder(__('analytics.all_languages'))
                    ->options(collect(Locales::all())->mapWithKeys(fn (string $l): array => [$l => Locales::nativeName($l)])->all()),
            ])
            ->emptyStateHeading(__('analytics.empty'))
            ->emptyStateIcon(Heroicon::OutlinedChartBar);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function report(array $filters): TrafficReport
    {
        [$from, $to] = ReportPeriod::range($this->period($filters));

        return new TrafficReport($from, $to, $this->language($filters));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function dimension(array $filters): string
    {
        $value = Arr::get($filters, 'dimension.value');

        return in_array($value, TrafficReport::DIMENSIONS, true) ? (string) $value : 'pages';
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function period(array $filters): string
    {
        $value = (string) Arr::get($filters, 'period.value', '');

        return in_array($value, ReportPeriod::KEYS, true) ? $value : '30';
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function language(array $filters): ?string
    {
        $value = Arr::get($filters, 'language.value');

        return Locales::isSupported(is_string($value) ? $value : null) ? (string) $value : null;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function periodText(array $filters): string
    {
        [$from, $to] = ReportPeriod::range($this->period($filters));
        $calendar = app(EthiopianCalendar::class);

        return $calendar->format($from).' – '.$calendar->format($to);
    }
}
