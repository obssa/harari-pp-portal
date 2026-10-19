<?php

namespace App\Filament\Widgets;

use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\PostTranslation;
use App\Domain\Dashboard\Contracts\DashboardWidget;
use App\Domain\Translation\Support\Locales;
use App\Filament\Widgets\Concerns\IsDashboardWidget;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The editorial pipeline (PLAN.md §7): how many post languages are in each
 * status, per language, for the chosen office.
 */
class EditorialPipeline extends Widget implements DashboardWidget
{
    use IsDashboardWidget;

    protected string $view = 'filament.widgets.editorial-pipeline';

    public static function key(): string
    {
        return 'pipeline';
    }

    public static function title(): string
    {
        return __('dashboard.widget.pipeline');
    }

    public static function permission(): ?string
    {
        return 'posts.view';
    }

    public static function defaultSize(): int
    {
        return 8;
    }

    /**
     * @return array{languages: list<string>, rows: list<array{status: ContentStatus, counts: array<string, int>, total: int}>}
     */
    public function pipeline(): array
    {
        $filters = $this->filters();
        $languages = $filters->locale !== null ? [$filters->locale] : Locales::all();

        $counts = PostTranslation::query()
            ->whereIn('locale', $languages)
            ->whereHas('post', fn (Builder $q) => $q->when($filters->officeId, fn (Builder $p, string $id) => $p->whereHas('offices', fn (Builder $o) => $o->whereKey($id))))
            ->selectRaw('status, locale, count(*) as aggregate')
            ->groupBy('status', 'locale')
            ->get()
            ->groupBy(fn (PostTranslation $row): string => $row->status->value);

        $rows = [];
        foreach (ContentStatus::cases() as $status) {
            $byLocale = [];
            foreach ($languages as $language) {
                $row = $counts->get($status->value)?->first(fn (PostTranslation $r): bool => $r->locale === $language);
                $byLocale[$language] = (int) ($row?->getAttribute('aggregate') ?? 0);
            }
            $rows[] = ['status' => $status, 'counts' => $byLocale, 'total' => array_sum($byLocale)];
        }

        return ['languages' => $languages, 'rows' => $rows];
    }
}
