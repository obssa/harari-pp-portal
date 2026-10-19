<?php

namespace App\Filament\Widgets;

use App\Domain\Dashboard\Contracts\DashboardWidget;
use App\Filament\Pages\ManageHomepage;
use App\Filament\Resources\Leaders\LeaderResource;
use App\Filament\Resources\Media\MediaResource;
use App\Filament\Resources\Offices\OfficeResource;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Widgets\Concerns\IsDashboardWidget;
use App\Models\User;
use Filament\Widgets\Widget;

/**
 * Shortcuts to the work each person does most (PLAN.md §7 quick actions),
 * limited to what they may do.
 */
class QuickActions extends Widget implements DashboardWidget
{
    use IsDashboardWidget;

    protected string $view = 'filament.widgets.quick-actions';

    public static function key(): string
    {
        return 'quick';
    }

    public static function title(): string
    {
        return __('dashboard.widget.quick');
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
     * @return list<array{label: string, url: string}>
     */
    public function actions(): array
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return [];
        }

        $actions = [
            ['posts.create', __('dashboard.quick.new_post'), fn (): string => PostResource::getUrl('create')],
            ['pages.create', __('dashboard.quick.new_page'), fn (): string => PageResource::getUrl('create')],
            ['leaders.manage', __('dashboard.quick.new_leader'), fn (): string => LeaderResource::getUrl('create')],
            ['offices.manage', __('dashboard.quick.new_office'), fn (): string => OfficeResource::getUrl('create')],
            ['media.upload', __('dashboard.quick.media'), fn (): string => MediaResource::getUrl()],
            ['homepage.manage', __('dashboard.quick.homepage'), fn (): string => ManageHomepage::getUrl()],
        ];

        $allowed = [];
        foreach ($actions as [$permission, $label, $url]) {
            if ($user->can($permission)) {
                $allowed[] = ['label' => $label, 'url' => $url()];
            }
        }

        return $allowed;
    }
}
