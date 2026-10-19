<?php

namespace App\Filament\Widgets;

use App\Domain\Dashboard\Contracts\DashboardWidget;
use App\Filament\Widgets\Concerns\IsDashboardWidget;
use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Background work (PLAN.md §7 queue health, ADR-0002): jobs waiting per
 * queue and the oldest one, failed jobs, and when the scheduler last ran.
 * On the shared host both are driven by cron every minute.
 */
class QueueHealth extends Widget implements DashboardWidget
{
    use IsDashboardWidget;

    public const HEARTBEAT = 'portal:scheduler-heartbeat';

    protected string $view = 'filament.widgets.queue-health';

    public static function key(): string
    {
        return 'queues';
    }

    public static function title(): string
    {
        return __('dashboard.widget.queues');
    }

    public static function permission(): ?string
    {
        return 'settings.view';
    }

    public static function defaultSize(): int
    {
        return 6;
    }

    /**
     * @return array{queues: list<array{queue: string, waiting: int, oldest: ?CarbonImmutable}>, failed: int, heartbeat: ?CarbonImmutable, healthy: bool}
     */
    public function health(): array
    {
        $queues = DB::table('jobs')
            ->selectRaw('queue, count(*) as waiting, min(created_at) as oldest')
            ->groupBy('queue')
            ->orderBy('queue')
            ->get()
            ->map(fn (object $row): array => [
                'queue' => (string) $row->queue,
                'waiting' => (int) $row->waiting,
                'oldest' => $row->oldest !== null ? CarbonImmutable::createFromTimestamp((int) $row->oldest) : null,
            ])
            ->all();

        $beat = Cache::get(self::HEARTBEAT);
        $heartbeat = is_string($beat) ? CarbonImmutable::parse($beat) : null;
        $oldest = collect($queues)->pluck('oldest')->filter()->min();

        return [
            'queues' => array_values($queues),
            'failed' => DB::table('failed_jobs')->count(),
            'heartbeat' => $heartbeat,
            'healthy' => $heartbeat !== null && $heartbeat->greaterThan(now()->subMinutes(5))
                && ($oldest === null || $oldest->greaterThan(now()->subMinutes(15))),
        ];
    }
}
