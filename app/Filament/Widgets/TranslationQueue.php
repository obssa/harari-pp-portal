<?php

namespace App\Filament\Widgets;

use App\Domain\Content\Models\PostTranslation;
use App\Domain\Dashboard\Contracts\DashboardWidget;
use App\Domain\Translation\Support\Locales;
use App\Domain\Translation\Support\TranslationRows;
use App\Filament\Pages\ManageTranslations;
use App\Filament\Widgets\Concerns\IsDashboardWidget;
use Filament\Widgets\Widget;

/**
 * What waits for translators (PLAN.md §7 translation queue, ADR-0035): per
 * language the interface strings missing, out of date after an English
 * change, or machine translated and not yet checked; and post languages
 * filled in by a machine.
 */
class TranslationQueue extends Widget implements DashboardWidget
{
    use IsDashboardWidget;

    protected string $view = 'filament.widgets.translation-queue';

    public static function key(): string
    {
        return 'translation_queue';
    }

    public static function title(): string
    {
        return __('dashboard.widget.translation_queue');
    }

    public static function permission(): ?string
    {
        return 'translations.view';
    }

    public static function defaultSize(): int
    {
        return 6;
    }

    /**
     * @return list<array{locale: string, name: string, missing: int, changed: int, machine: int, posts: int, url: string}>
     */
    public function queue(): array
    {
        $rows = [];
        foreach (array_values(array_diff(Locales::all(), ['en'])) as $locale) {
            $statuses = app(TranslationRows::class)->for($locale)->countBy('status');
            $rows[] = [
                'locale' => $locale,
                'name' => Locales::nativeName($locale),
                'missing' => (int) ($statuses[TranslationRows::MISSING] ?? 0),
                'changed' => (int) ($statuses[TranslationRows::CHANGED] ?? 0),
                'machine' => (int) ($statuses[TranslationRows::MACHINE] ?? 0),
                'posts' => PostTranslation::query()->where('locale', $locale)->whereNotNull('machine_translated_at')->count(),
                'url' => ManageTranslations::getUrl(['language' => $locale]),
            ];
        }

        return $rows;
    }
}
