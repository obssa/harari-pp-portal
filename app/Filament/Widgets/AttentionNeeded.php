<?php

namespace App\Filament\Widgets;

use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\PostTranslation;
use App\Domain\Dashboard\Contracts\DashboardWidget;
use App\Domain\Media\Models\Media;
use App\Domain\Search\Models\SearchTerm;
use App\Filament\Pages\ManageSecurity;
use App\Filament\Resources\Media\MediaResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\SearchTerms\SearchTermResource;
use App\Filament\Widgets\Concerns\IsDashboardWidget;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/**
 * What needs someone's attention now (PLAN.md §7): posts waiting too long
 * for review, scheduled posts that did not go live, failed background jobs,
 * images without alt text, searches that found nothing and senior accounts
 * without two-step sign-in. Each line is shown only to those who can act.
 */
class AttentionNeeded extends Widget implements DashboardWidget
{
    use IsDashboardWidget;

    protected string $view = 'filament.widgets.attention-needed';

    public static function key(): string
    {
        return 'attention';
    }

    public static function title(): string
    {
        return __('dashboard.widget.attention');
    }

    public static function permission(): ?string
    {
        return null;
    }

    public static function defaultSize(): int
    {
        return 6;
    }

    /**
     * @return list<array{label: string, count: int, url: ?string, tone: string}>
     */
    public function items(): array
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return [];
        }

        $items = [];
        if ($user->can('posts.review') || $user->can('posts.publish')) {
            $items[] = [
                'label' => __('dashboard.attention.review_waiting'),
                'count' => PostTranslation::query()->where('status', ContentStatus::InReview->value)->where('updated_at', '<', now()->subDay())->count(),
                'url' => PostResource::getUrl(),
                'tone' => 'warning',
            ];
            $items[] = [
                'label' => __('dashboard.attention.schedule_late'),
                'count' => PostTranslation::query()->where('status', ContentStatus::Scheduled->value)->where('published_at', '<', now()->subMinutes(10))->count(),
                'url' => PostResource::getUrl(),
                'tone' => 'danger',
            ];
        }
        if ($user->can('settings.view')) {
            $items[] = [
                'label' => __('dashboard.attention.failed_jobs'),
                'count' => DB::table('failed_jobs')->count(),
                'url' => null,
                'tone' => 'danger',
            ];
        }
        if ($user->can('media.view')) {
            $items[] = [
                'label' => __('dashboard.attention.missing_alt'),
                'count' => Media::query()->library()->missingAlt()->count(),
                'url' => MediaResource::getUrl(),
                'tone' => 'warning',
            ];
        }
        if ($user->can('analytics.view')) {
            $items[] = [
                'label' => __('dashboard.attention.searches_without_results'),
                'count' => SearchTerm::query()->where('last_results', 0)->where('last_searched_at', '>=', now()->subWeek())->count(),
                'url' => SearchTermResource::getUrl(),
                'tone' => 'gray',
            ];
        }
        if ($user->isSuperAdmin()) {
            $items[] = [
                'label' => __('dashboard.attention.senior_without_app'),
                'count' => ManageSecurity::coveredWithoutApp(),
                'url' => ManageSecurity::getUrl(),
                'tone' => 'gray',
            ];
        }

        return array_values(array_filter($items, fn (array $item): bool => $item['count'] > 0));
    }
}
