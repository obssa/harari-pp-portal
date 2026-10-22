<?php

namespace App\Filament\Pages;

use App\Domain\Dashboard\Support\DashboardFilters;
use App\Domain\Organisation\Models\Office;
use App\Domain\Translation\Support\Locales;
use App\Filament\Support\CurrentUser;
use App\Filament\Support\DashboardRegistry;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;

/**
 * The staff dashboard (PLAN.md §7, ADR-0021): range, language and office
 * filters on top, then the person's own widget layout, which they can
 * change (add, remove, reorder, resize) or reset to their role's default.
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    /**
     * ADR-0064: a welcome band with the date in both calendars, keeping the
     * page's actions.
     */
    public function getHeader(): ?View
    {
        $name = trim((string) CurrentUser::get()->name);

        return view('filament.dashboard.welcome', [
            'actions' => $this->getCachedHeaderActions(),
            'name' => $name === '' ? '' : (string) strtok($name, ' '),
        ]);
    }

    public function getColumns(): int|array
    {
        return 12;
    }

    public function getWidgets(): array
    {
        return DashboardRegistry::widgetsFor(CurrentUser::get());
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('range')
                ->label(__('dashboard.filter.range'))
                ->options(collect(DashboardFilters::RANGES)->mapWithKeys(fn (string $r): array => [$r => __("dashboard.range.{$r}")])->all())
                ->default('30')
                ->selectablePlaceholder(false),
            Select::make('locale')
                ->label(__('dashboard.filter.locale'))
                ->placeholder(__('dashboard.filter.all_languages'))
                ->options(collect(Locales::all())->mapWithKeys(fn (string $l): array => [$l => Locales::nativeName($l)])->all()),
            Select::make('office')
                ->label(__('dashboard.filter.office'))
                ->placeholder(__('dashboard.filter.all_offices'))
                ->searchable()
                ->options(fn (): array => Office::query()->with('translations')->orderBy('sort')->get()
                    ->mapWithKeys(fn (Office $o): array => [$o->id => $o->name()])->all()),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('customise')
                ->label(__('dashboard.customise'))
                ->icon(Heroicon::OutlinedSquares2x2)
                ->modalHeading(__('dashboard.customise'))
                ->modalDescription(__('dashboard.customise_help'))
                ->fillForm(fn (): array => ['widgets' => DashboardRegistry::layoutFor(CurrentUser::get())])
                ->schema([
                    Repeater::make('widgets')
                        ->hiddenLabel()
                        ->reorderableWithDragAndDrop()
                        ->reorderableWithButtons()
                        ->maxItems(DashboardRegistry::MAX_WIDGETS)
                        ->addActionLabel(__('dashboard.add_widget'))
                        ->columns(2)
                        ->schema([
                            Select::make('key')
                                ->label(__('dashboard.widget_label'))
                                ->options(fn (): array => collect(DashboardRegistry::availableTo(CurrentUser::get()))
                                    ->map(fn (string $widget): string => $widget::title())->all())
                                ->required(),
                            Select::make('size')
                                ->label(__('dashboard.size_label'))
                                ->options(collect(DashboardRegistry::SIZES)->mapWithKeys(fn (int $s): array => [$s => __("dashboard.size.{$s}")])->all())
                                ->default(6)
                                ->required(),
                        ]),
                ])
                ->action(function (array $data): void {
                    DashboardRegistry::save(CurrentUser::get(), $data['widgets'] ?? []);
                    Notification::make()->success()->title(__('dashboard.saved'))->send();
                    $this->redirect(static::getUrl());
                }),
            Action::make('reset')
                ->label(__('dashboard.reset'))
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription(__('dashboard.reset_help'))
                ->action(function (): void {
                    DashboardRegistry::reset(CurrentUser::get());
                    Notification::make()->success()->title(__('dashboard.reset_done'))->send();
                    $this->redirect(static::getUrl());
                }),
        ];
    }
}
