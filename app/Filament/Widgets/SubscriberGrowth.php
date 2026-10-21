<?php

namespace App\Filament\Widgets;

use App\Domain\Dashboard\Contracts\DashboardWidget;
use App\Domain\Engagement\Models\Subscriber;
use App\Filament\Resources\Subscribers\SubscriberResource;
use App\Filament\Widgets\Concerns\IsDashboardWidget;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/**
 * Subscribers (PLAN.md §7, ADR-0053): who can be reached by email and by SMS,
 * new confirmed sign-ups in the period against the one before, and those
 * still waiting to confirm.
 */
class SubscriberGrowth extends StatsOverviewWidget implements DashboardWidget
{
    use IsDashboardWidget;

    protected ?string $pollingInterval = null;

    public static function key(): string
    {
        return 'subscribers';
    }

    public static function title(): string
    {
        return __('campaigns.dashboard.subscribers');
    }

    public static function permission(): ?string
    {
        return 'subscribers.view';
    }

    public static function defaultSize(): int
    {
        return 12;
    }

    protected function getStats(): array
    {
        $filters = $this->filters();
        $confirmed = fn ($from, $to): int => Subscriber::query()->whereNull('erased_at')
            ->where(fn ($q) => $q->whereBetween('email_confirmed_at', [$from, $to])->orWhereBetween('phone_confirmed_at', [$from, $to]))->count();
        $new = $confirmed($filters->from, $filters->to);
        $delta = $new - $confirmed($filters->previousFrom(), $filters->from->subSecond());
        $pending = Subscriber::query()->whereNull('erased_at')->whereNull('unsubscribed_at')->whereNull('email_confirmed_at')->whereNull('phone_confirmed_at')->count();

        return [
            Stat::make(__('campaigns.dashboard.by_email'), Number::format(Subscriber::query()->reachable('email')->count()))->url(SubscriberResource::getUrl()),
            Stat::make(__('campaigns.dashboard.by_sms'), Number::format(Subscriber::query()->reachable('sms')->count()))->url(SubscriberResource::getUrl()),
            Stat::make(__('campaigns.dashboard.new'), Number::format($new))
                ->description(__('dashboard.kpi.versus_previous', ['delta' => ($delta >= 0 ? '+' : '').$delta]))
                ->descriptionIcon($delta >= 0 ? Heroicon::ArrowTrendingUp : Heroicon::ArrowTrendingDown)
                ->color($delta >= 0 ? 'success' : 'warning'),
            Stat::make(__('campaigns.dashboard.pending'), Number::format($pending))->description(__('campaigns.dashboard.pending_help')),
        ];
    }
}
