<?php

namespace App\Filament\Support;

use App\Domain\Dashboard\Contracts\DashboardWidget;
use App\Domain\Dashboard\Models\DashboardLayout;
use App\Filament\Widgets\ActivityFeed;
use App\Filament\Widgets\Announcements;
use App\Filament\Widgets\AttentionNeeded;
use App\Filament\Widgets\EditorialPipeline;
use App\Filament\Widgets\KpiStrip;
use App\Filament\Widgets\MediaStorage;
use App\Filament\Widgets\QueueHealth;
use App\Filament\Widgets\QuickActions;
use App\Filament\Widgets\SearchInsights;
use App\Filament\Widgets\TopPages;
use App\Filament\Widgets\TrafficOverview;
use App\Filament\Widgets\TranslationCoverage;
use App\Filament\Widgets\TranslationQueue;
use App\Models\User;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

/**
 * The dashboard widget registry (PLAN.md §7, ADR-0021). The order here is
 * the default layout; each role's default is the part of it the role may
 * see, so a Publisher starts with the editorial widgets and an Admin also
 * gets operations.
 */
final class DashboardRegistry
{
    /** Widths offered, in columns of 12. */
    public const SIZES = [3, 4, 6, 8, 12];

    /** At most this many widgets in one layout. */
    public const MAX_WIDGETS = 16;

    /** @var list<class-string<Widget&DashboardWidget>> */
    private const WIDGETS = [
        Announcements::class,
        KpiStrip::class,
        AttentionNeeded::class,
        QuickActions::class,
        EditorialPipeline::class,
        TranslationCoverage::class,
        TranslationQueue::class,
        SearchInsights::class,
        TrafficOverview::class,
        TopPages::class,
        MediaStorage::class,
        QueueHealth::class,
        ActivityFeed::class,
    ];

    /**
     * @return array<string, class-string<Widget&DashboardWidget>>
     */
    public static function all(): array
    {
        $widgets = [];
        foreach (self::WIDGETS as $widget) {
            $widgets[$widget::key()] = $widget;
        }

        return $widgets;
    }

    /**
     * @return array<string, class-string<Widget&DashboardWidget>>
     */
    public static function availableTo(User $user): array
    {
        return array_filter(self::all(), fn (string $widget): bool => $widget::permission() === null || $user->can($widget::permission()));
    }

    /**
     * The user's saved layout without widgets they may no longer see, or
     * the default for their roles.
     *
     * @return list<array{key: string, size: int}>
     */
    public static function layoutFor(User $user): array
    {
        $available = self::availableTo($user);
        $saved = DashboardLayout::query()->where('user_id', $user->id)->value('widgets');

        if (is_array($saved)) {
            return self::clean($saved, $available);
        }

        return array_values(array_map(
            fn (string $widget): array => ['key' => $widget::key(), 'size' => $widget::defaultSize()],
            $available,
        ));
    }

    /**
     * @param  array<mixed>  $widgets
     * @param  array<string, class-string<Widget&DashboardWidget>>  $available
     * @return list<array{key: string, size: int}>
     */
    public static function clean(array $widgets, array $available): array
    {
        $layout = [];
        foreach ($widgets as $item) {
            $key = is_array($item) ? (string) ($item['key'] ?? '') : '';
            if (! isset($available[$key])) {
                continue;
            }
            $size = (int) ($item['size'] ?? 0);
            $layout[] = ['key' => $key, 'size' => in_array($size, self::SIZES, true) ? $size : $available[$key]::defaultSize()];
        }

        return array_slice($layout, 0, self::MAX_WIDGETS);
    }

    /**
     * @return list<WidgetConfiguration>
     */
    public static function widgetsFor(User $user): array
    {
        $available = self::availableTo($user);

        return array_map(
            fn (array $item): WidgetConfiguration => $available[$item['key']]::make([
                'columnSpan' => ['default' => 12, 'lg' => $item['size']],
            ]),
            self::layoutFor($user),
        );
    }

    public static function save(User $user, mixed $widgets): void
    {
        DashboardLayout::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['widgets' => self::clean(is_array($widgets) ? array_values($widgets) : [], self::availableTo($user))],
        );
    }

    public static function reset(User $user): void
    {
        DashboardLayout::query()->where('user_id', $user->id)->delete();
    }
}
