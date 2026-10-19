<?php

namespace App\Filament\Widgets;

use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\Post;
use App\Domain\Dashboard\Contracts\DashboardWidget;
use App\Domain\Translation\Support\Locales;
use App\Domain\Translation\Support\TranslationCatalog;
use App\Filament\Widgets\Concerns\IsDashboardWidget;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Translation coverage (PLAN.md §7): share of interface texts translated
 * per language, and share of published posts available in each language.
 */
class TranslationCoverage extends Widget implements DashboardWidget
{
    use IsDashboardWidget;

    protected string $view = 'filament.widgets.translation-coverage';

    public static function key(): string
    {
        return 'coverage';
    }

    public static function title(): string
    {
        return __('dashboard.widget.coverage');
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
     * @return list<array{locale: string, name: string, interface: float, posts: float}>
     */
    public function coverage(): array
    {
        $catalog = app(TranslationCatalog::class);
        $published = Post::query()->whereHas('translations', fn (Builder $q) => $q->where('status', ContentStatus::Published->value))->count();

        $rows = [];
        foreach (Locales::all() as $locale) {
            $withLanguage = $published === 0 ? 0 : Post::query()
                ->whereHas('translations', fn (Builder $q) => $q->where('locale', $locale)->where('status', ContentStatus::Published->value))
                ->count();
            $rows[] = [
                'locale' => $locale,
                'name' => Locales::nativeName($locale),
                'interface' => $locale === 'en' ? 100.0 : $catalog->coverage($locale)['percent'],
                'posts' => $published === 0 ? 0.0 : round($withLanguage / $published * 100, 1),
            ];
        }

        return $rows;
    }
}
