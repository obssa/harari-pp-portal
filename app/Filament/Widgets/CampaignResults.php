<?php

namespace App\Filament\Widgets;

use App\Domain\Dashboard\Contracts\DashboardWidget;
use App\Domain\Engagement\Enums\CampaignStatus;
use App\Domain\Engagement\Models\Campaign;
use App\Domain\Engagement\Support\CampaignReport;
use App\Filament\Widgets\Concerns\IsDashboardWidget;
use Filament\Widgets\Widget;

/**
 * Campaign results (PLAN.md §7, ADR-0053): the campaigns that went out in the
 * period, with how many were sent, clicked and unsubscribed.
 */
class CampaignResults extends Widget implements DashboardWidget
{
    use IsDashboardWidget;

    protected string $view = 'filament.widgets.campaign-results';

    public static function key(): string
    {
        return 'campaigns';
    }

    public static function title(): string
    {
        return __('campaigns.dashboard.results');
    }

    public static function permission(): ?string
    {
        return 'campaigns.view';
    }

    public static function defaultSize(): int
    {
        return 6;
    }

    /**
     * @return list<array{campaign: Campaign, sent: int, clicked: float, unsubscribed: float}>
     */
    public function rows(): array
    {
        $filters = $this->filters();

        return Campaign::query()
            ->whereIn('status', [CampaignStatus::Sending->value, CampaignStatus::Sent->value, CampaignStatus::Cancelled->value])
            ->whereBetween('started_at', [$filters->from, $filters->to])
            ->latest('started_at')->limit(5)->get()
            ->map(function (Campaign $campaign): array {
                $totals = (new CampaignReport($campaign))->totals();

                return [
                    'campaign' => $campaign,
                    'sent' => $totals['sent'],
                    'clicked' => CampaignReport::rate($totals['clicked'], $totals['sent']),
                    'unsubscribed' => CampaignReport::rate($totals['unsubscribed'], $totals['sent']),
                ];
            })->values()->all();
    }
}
